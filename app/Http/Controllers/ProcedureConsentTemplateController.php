<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Exports\ProcedureConsentTemplateWorkbook;
use App\Imports\ProcedureConsentTemplateImport;
use App\Models\ClinicalTerminology;
use App\Models\ProcedureConsentTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReadException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProcedureConsentTemplateController extends Controller
{
    public function index(): Response
    {
        $templates = ProcedureConsentTemplate::withTrashed()->latest('id')->get();
        $current = $templates->whereNotNull('published_at')->sortByDesc('published_at')
            ->unique(fn (ProcedureConsentTemplate $row): string => json_encode([$row->code, $row->release, $row->clinic_type], JSON_THROW_ON_ERROR))
            ->reject(fn (ProcedureConsentTemplate $row): bool => $row->trashed())
            ->pluck('id')->flip();

        return Inertia::render('master/terminologi/consent-templates', [
            'templates' => $templates->map(fn (ProcedureConsentTemplate $row): array => [
                ...$row->toArray(), 'active' => $current->has($row->id),
            ]),
            'releases' => ClinicalTerminology::where('code_system', 'icd9cm_procedure')
                ->where('is_active', true)->distinct()->orderBy('release')->pluck('release'),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        return Excel::download(new ProcedureConsentTemplateWorkbook, 'template-sop-persetujuan.xlsx');
    }

    public function export(Request $request, ClinicalAuditRecorder $audit): BinaryFileResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'poli' => ['nullable', 'in:umum,gigi'],
            'rilis' => ['nullable', 'string', 'max:60'],
            'tab' => ['nullable', 'in:template,arsip'],
        ]);
        $archived = ($filters['tab'] ?? null) === 'arsip';
        $rows = ($archived ? ProcedureConsentTemplate::onlyTrashed() : ProcedureConsentTemplate::query())
            ->when($filters['poli'] ?? null, fn (Builder $query, string $clinic) => $query->where('clinic_type', $clinic))
            ->when($filters['rilis'] ?? null, fn (Builder $query, string $release) => $query->where('release', $release))
            ->when(filled($filters['search'] ?? null), function (Builder $query) use ($filters): void {
                $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($filters['search'])).'%';
                $query->where(fn (Builder $matches) => $matches
                    ->whereRaw("LOWER(code) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(version) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(sop_reference) LIKE ? ESCAPE '!'", [$term]));
            })->latest('id');
        $audit->record($request, 'procedure_consent_template.exported', metadata: ['archived' => $archived]);

        return Excel::download(new ProcedureConsentTemplateWorkbook($rows), 'sop-persetujuan-'.($archived ? 'arsip-' : '').today()->toDateString().'.xlsx');
    }

    public function import(Request $request, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate(['file' => ['required', 'file', 'mimes:xlsx', 'max:2048']]);
        $import = new ProcedureConsentTemplateImport(fn (array $values): array => $this->rules($values));
        try {
            DB::transaction(function () use ($import, $data, $audit, $request): void {
                Excel::import($import, $data['file']);
                $audit->record($request, 'procedure_consent_template.imported', metadata: ['count' => $import->count]);
            });
        } catch (SpreadsheetReadException|\InvalidArgumentException) {
            throw ValidationException::withMessages(['file' => 'Berkas belum bisa dibaca sebagai Excel. Gunakan template .xlsx yang tersedia.']);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['file' => 'Versi template sudah tersedia. Gunakan versi baru untuk kombinasi kode, rilis, dan poli yang sama.']);
        }

        return redirect()->route('master.consent-templates.index')->with('success', "{$import->count} template SOP berhasil diimpor sebagai draf.");
    }

    public function store(Request $request, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $template = ProcedureConsentTemplate::create($this->validated($request));
        $audit->record($request, 'procedure_consent_template.draft', metadata: ['template_id' => $template->id]);

        return back()->with('success', 'Draf template disimpan.');
    }

    public function update(Request $request, ProcedureConsentTemplate $template, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $template, $audit): void {
            $draft = ProcedureConsentTemplate::lockForUpdate()->findOrFail($template->id);
            $draft->fill($this->validated($request, $draft));
            if ($draft->published_at !== null) {
                $request->validate(['approved' => ['accepted']]);
                if ($draft->isDirty(['code', 'release', 'clinic_type'])) {
                    throw ValidationException::withMessages(['code' => 'Untuk kode, rilis, atau poli lain, buat template baru.']);
                }
                $this->ensureComplete($draft);
            }
            $draft->save();
            $audit->record($request, 'procedure_consent_template.updated', metadata: ['template_id' => $draft->id, 'revision' => $draft->revision()]);
        });

        return back()->with('success', 'Template diperbarui.');
    }

    public function publish(Request $request, ProcedureConsentTemplate $template, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $request->validate(['approved' => ['accepted']]);

        DB::transaction(function () use ($request, $template, $audit): void {
            $draft = ProcedureConsentTemplate::lockForUpdate()->findOrFail($template->id);
            abort_if($draft->published_at !== null, 409);
            $this->ensureComplete($draft);

            $draft->forceFill(['published_by' => $request->user()->id, 'published_at' => now()])->save();
            $audit->record($request, 'procedure_consent_template.published', metadata: ['template_id' => $draft->id, 'version' => $draft->version]);
        });

        return back()->with('success', 'Versi template diterbitkan.');
    }

    public function destroy(Request $request, ProcedureConsentTemplate $template, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $template, $audit): void {
            $row = ProcedureConsentTemplate::lockForUpdate()->findOrFail($template->id);
            $row->delete();
            $audit->record($request, 'procedure_consent_template.archived', metadata: ['template_id' => $row->id]);
        });

        return back()->with('success', 'Template diarsipkan dan dapat dipulihkan.');
    }

    public function restore(Request $request, int $template, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $template, $audit): void {
            $row = ProcedureConsentTemplate::onlyTrashed()->lockForUpdate()->findOrFail($template);
            $row->restore();
            $audit->record($request, 'procedure_consent_template.restored', metadata: ['template_id' => $row->id]);
        });

        return back()->with('success', 'Template dipulihkan.');
    }

    private function ensureComplete(ProcedureConsentTemplate $template): void
    {
        $missingExplanation = collect(ProcedureConsentTemplate::FIELDS)
            ->contains(fn (string $field): bool => blank($template->explanation[$field] ?? null));
        if (blank($template->sop_reference) || $missingExplanation || ($template->mode === 'conditional' && blank($template->trigger_condition))) {
            throw ValidationException::withMessages(['approved' => 'Lengkapi referensi SOP, ketujuh bagian penjelasan, dan kondisi pemicu jika kondisional.']);
        }
    }

    private function validated(Request $request, ?ProcedureConsentTemplate $draft = null): array
    {
        return $request->validate($this->rules($request->only(['code', 'release', 'clinic_type']), $draft));
    }

    private function rules(array $data, ?ProcedureConsentTemplate $draft = null): array
    {
        return [
            'code' => ['required', 'string', 'max:32', Rule::exists('clinical_terminologies', 'code')
                ->where('code_system', 'icd9cm_procedure')->where('release', (is_string($data['release'] ?? null) ? $data['release'] : ''))->where('is_active', true)],
            'release' => ['required', 'string', 'max:60'],
            'clinic_type' => ['required', Rule::in(['umum', 'gigi'])],
            'version' => ['required', 'string', 'max:40', Rule::unique('procedure_consent_templates')
                ->where('code', (is_string($data['code'] ?? null) ? $data['code'] : ''))
                ->where('release', (is_string($data['release'] ?? null) ? $data['release'] : ''))
                ->where('clinic_type', (is_string($data['clinic_type'] ?? null) ? $data['clinic_type'] : ''))->ignore($draft?->id)],
            'mode' => ['required', Rule::in(['oral', 'written', 'conditional'])],
            'trigger_condition' => ['nullable', 'string', 'max:2000'],
            'sop_reference' => ['nullable', 'string', 'max:255'],
            'explanation' => ['nullable', 'array:'.implode(',', ProcedureConsentTemplate::FIELDS)],
            'explanation.*' => ['nullable', 'string', 'max:3000'],
        ];
    }
}
