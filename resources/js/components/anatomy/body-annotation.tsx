import { useEffect, useId, useRef, useState, type PointerEvent } from 'react';
import { ArrowUpRight, Circle, Eraser, Minus, Pencil, Redo2, Trash2, Undo2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import { confirmAction } from '@/components/ui/confirm-dialog';
import { annotationColors, annotationLimits, annotationPoint, type AnnotationStroke, type AnnotationTool, type BodyAnnotation } from '@/lib/body-map';

export function AnnotationShapes({ strokes, onErase }: { strokes: AnnotationStroke[]; onErase?: (id: string) => void }) {
    return <>{strokes.map((stroke) => {
        const first = stroke.points[0];
        const last = stroke.points.at(-1);
        if (!first || !last) return null;
        const shared = { stroke: stroke.color, strokeWidth: stroke.width, fill: 'none', strokeLinecap: 'round' as const, strokeLinejoin: 'round' as const };
        let shape;
        if (stroke.tool === 'ellipse') {
            shape = <ellipse {...shared} cx={(first.x + last.x) / 2} cy={(first.y + last.y) / 2} rx={Math.abs(last.x - first.x) / 2} ry={Math.abs(last.y - first.y) / 2} />;
        } else if (stroke.tool === 'pen') {
            shape = stroke.points.length === 1 ? <circle cx={first.x} cy={first.y} r={stroke.width / 2} fill={stroke.color} /> : <polyline {...shared} points={stroke.points.map((point) => point.x + ',' + point.y).join(' ')} />;
        } else {
            const angle = Math.atan2(last.y - first.y, last.x - first.x);
            const size = 10 + stroke.width;
            const a = { x: last.x - size * Math.cos(angle - Math.PI / 6), y: last.y - size * Math.sin(angle - Math.PI / 6) };
            const b = { x: last.x - size * Math.cos(angle + Math.PI / 6), y: last.y - size * Math.sin(angle + Math.PI / 6) };
            shape = <><line {...shared} x1={first.x} y1={first.y} x2={last.x} y2={last.y} />{stroke.tool === 'arrow' && <polyline {...shared} points={`${a.x},${a.y} ${last.x},${last.y} ${b.x},${b.y}`} />}</>;
        }
        return <g key={stroke.id} data-annotation-id={stroke.id} onPointerDown={onErase ? (event) => { event.stopPropagation(); onErase(stroke.id); } : undefined} className={onErase ? 'cursor-pointer hover:opacity-40' : undefined}>{shape}</g>;
    })}</>;
}

export function BodyAnnotationView({ value, children }: { value: BodyAnnotation; children?: React.ReactNode }) {
    return <figure className="min-w-0 space-y-3" aria-label="Gambar anotasi pemeriksaan tubuh">
        <div className="relative overflow-hidden rounded-xl border border-neutral-200 bg-white">
            <img src="/body-model-reference.jpg" alt="Panduan tubuh laki-laki dan perempuan tampak depan dan belakang, dengan detail kepala, tangan, dan kaki" width="592" height="558" className="block h-auto w-full select-none" draggable={false} />
            <svg viewBox="0 0 592 558" className="pointer-events-none absolute inset-0 h-full w-full" aria-label="Anotasi tubuh tersimpan" role="img"><AnnotationShapes strokes={value.strokes} /></svg>
            {children}
        </div>
        {value.note && <figcaption className="whitespace-pre-wrap break-words text-sm text-neutral-700">{value.note}</figcaption>}
    </figure>;
}

export function BodyAnnotationEditor({ value, onChange, disabled = false, showNote = true }: { value: BodyAnnotation; onChange: (value: BodyAnnotation) => void; disabled?: boolean; showNote?: boolean }) {
    const id = useId();
    const [tool, setTool] = useState<AnnotationTool | 'erase'>('pen');
    const [color, setColor] = useState(annotationColors[0]);
    const [width, setWidth] = useState(2);
    const [redo, setRedo] = useState<AnnotationStroke[]>([]);
    const [message, setMessage] = useState('');
    const current = useRef(value);
    const ownStrokes = useRef(value.strokes);
    const drawing = useRef<{ id: string; pointerId: number } | null>(null);
    current.current = value;
    useEffect(() => {
        if (value.strokes !== ownStrokes.current) setRedo([]);
        ownStrokes.current = value.strokes;
    }, [value.strokes]);
    function commit(strokes: AnnotationStroke[]) {
        ownStrokes.current = strokes;
        current.current = { ...current.current, strokes };
        onChange(current.current);
    }
    function begin(event: PointerEvent<SVGSVGElement>) {
        if (disabled || tool === 'erase' || !event.isPrimary || event.button !== 0 || drawing.current) return;
        const strokes = current.current.strokes;
        if (strokes.length >= annotationLimits.strokes || strokes.reduce((sum, stroke) => sum + stroke.points.length, 0) + (tool === 'pen' ? 1 : 2) > annotationLimits.totalPoints) { setMessage('Batas anotasi tercapai. Hapus goresan yang tidak diperlukan.'); return; }
        event.preventDefault();
        event.currentTarget.setPointerCapture(event.pointerId);
        const point = annotationPoint(event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
        const stroke: AnnotationStroke = { id: crypto.randomUUID(), tool, color, width, points: tool === 'pen' ? [point] : [point, point] };
        drawing.current = { id: stroke.id, pointerId: event.pointerId };
        setRedo([]);
        setMessage('');
        commit([...strokes, stroke]);
    }
    function move(event: PointerEvent<SVGSVGElement>) {
        if (disabled || drawing.current?.pointerId !== event.pointerId) return;
        const strokes = current.current.strokes;
        const active = strokes.find((stroke) => stroke.id === drawing.current?.id);
        if (!active) return;
        const point = annotationPoint(event.clientX, event.clientY, event.currentTarget.getBoundingClientRect());
        if (active.tool === 'pen') {
            const last = active.points.at(-1)!;
            if (Math.hypot(point.x - last.x, point.y - last.y) < 1.5) return;
            if (active.points.length >= annotationLimits.pointsPerStroke || strokes.reduce((sum, stroke) => sum + stroke.points.length, 0) >= annotationLimits.totalPoints) { setMessage('Batas detail goresan tercapai. Lepaskan pena untuk melanjutkan.'); return; }
        }
        commit(strokes.map((stroke) => stroke.id === active.id ? { ...stroke, points: stroke.tool === 'pen' ? [...stroke.points, point] : [stroke.points[0], point] } : stroke));
    }
    function finish(event: PointerEvent<SVGSVGElement>) {
        if (drawing.current?.pointerId !== event.pointerId) return;
        move(event);
        drawing.current = null;
        if (event.currentTarget.hasPointerCapture(event.pointerId)) event.currentTarget.releasePointerCapture(event.pointerId);
    }
    const tools = [{ id: 'pen', label: 'Pena', icon: Pencil }, { id: 'line', label: 'Garis', icon: Minus }, { id: 'arrow', label: 'Panah', icon: ArrowUpRight }, { id: 'ellipse', label: 'Lingkaran', icon: Circle }, { id: 'erase', label: 'Hapus goresan', icon: Eraser }] as const;
    return <section className="min-w-0 space-y-3" aria-label="Editor anotasi tubuh">
        {!disabled && <div className="space-y-2 rounded-xl border border-neutral-200 bg-neutral-50/60 p-2" aria-label="Pengaturan anotasi">
            <div className="flex flex-wrap items-center gap-2">
                <div className="flex flex-wrap gap-1" role="group" aria-label="Alat anotasi">{tools.map(({ id: item, label, icon: Icon }) => <Button key={item} type="button" size="icon" className="size-9" variant={tool === item ? 'default' : 'ghost'} aria-label={label} title={label} aria-pressed={tool === item} onClick={() => setTool(item)}><Icon className="size-4" /></Button>)}</div>
                <div className="flex gap-1 border-l border-neutral-200 pl-2" role="group" aria-label="Kelola goresan">
                    <Button type="button" size="icon" className="size-9" variant="ghost" disabled={!value.strokes.length} aria-label="Urungkan goresan" title="Urungkan" onClick={() => { const last = current.current.strokes.at(-1); if (last) { setRedo((items) => [...items, last]); commit(current.current.strokes.slice(0, -1)); } }}><Undo2 className="size-4" /></Button>
                    <Button type="button" size="icon" className="size-9" variant="ghost" disabled={!redo.length} aria-label="Ulangi goresan" title="Ulangi" onClick={() => { const last = redo.at(-1); if (last) { setRedo(redo.slice(0, -1)); commit([...current.current.strokes, last]); } }}><Redo2 className="size-4" /></Button>
                    <Button type="button" size="icon" className="size-9" variant="ghost" disabled={!value.strokes.length} aria-label="Hapus semua goresan" title="Hapus semua goresan" onClick={() => confirmAction('Hapus semua goresan pada gambar ini?', () => { commit([]); setRedo([]); }, 'Hapus goresan')}><Trash2 className="size-4" /></Button>
                </div>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-200 pt-2">
                <div className="flex items-center gap-1" role="group" aria-label="Warna pena">{annotationColors.map((item, index) => <Button type="button" size="icon" variant="ghost" key={item} title={['Merah', 'Biru', 'Hijau', 'Ungu', 'Hitam'][index]} aria-label={['Merah', 'Biru', 'Hijau', 'Ungu', 'Hitam'][index]} aria-pressed={color === item} onClick={() => setColor(item)} className={'size-8 rounded-lg border ' + (color === item ? 'border-neutral-500 bg-surface' : 'border-transparent')}><span className="size-4 rounded-full border border-black/10" style={{ backgroundColor: item }} /></Button>)}</div>
                <div className="flex items-center gap-1" role="group" aria-label="Ketebalan pena">{[2, 4, 6].map((item, index) => <Button type="button" size="icon" className="size-8" variant={width === item ? 'secondary' : 'ghost'} key={item} title={['Tipis', 'Sedang', 'Tebal'][index]} aria-label={'Ketebalan ' + ['tipis', 'sedang', 'tebal'][index]} aria-pressed={width === item} onClick={() => setWidth(item)}><span className="w-4 rounded-full bg-current" style={{ height: item }} /></Button>)}</div>
            </div>
        </div>}
        <BodyAnnotationView value={{ ...value, note: '' }}>
            {!disabled && <svg viewBox="0 0 592 558" className={'absolute inset-0 h-full w-full touch-none ' + (tool === 'erase' ? 'cursor-default' : 'cursor-crosshair')} role="img" aria-label="Kanvas anotasi tubuh; gunakan pena, mouse, atau sentuhan" onPointerDown={begin} onPointerMove={move} onPointerUp={finish} onPointerCancel={() => { drawing.current = null; }} onLostPointerCapture={() => { drawing.current = null; }}><g opacity={tool === 'erase' ? 1 : 0} pointerEvents={tool === 'erase' ? 'visiblePainted' : 'none'}><AnnotationShapes strokes={value.strokes} onErase={tool === 'erase' ? (strokeId) => { setRedo([]); commit(current.current.strokes.filter((stroke) => stroke.id !== strokeId)); } : undefined} /></g></svg>}
        </BodyAnnotationView>
        {!disabled && <p className="text-xs text-neutral-500">{tool === 'erase' ? 'Klik goresan yang ingin dihapus.' : 'Gambar pada referensi tubuh. Anotasi disimpan bersama pemeriksaan fisik.'} {value.strokes.length}/100 goresan.</p>}
        {message && <p role="alert" className="text-sm text-amber-700">{message}</p>}
        {showNote && <Field htmlFor={id + '-annotation-note'} label="Keterangan anotasi"><Textarea id={id + '-annotation-note'} rows={3} maxLength={2000} disabled={disabled} value={value.note} onChange={(event) => onChange({ ...value, note: event.target.value })} placeholder="Jelaskan tanda, sisi tubuh pasien, ukuran, atau temuan pada gambar" /></Field>}
    </section>;
}
