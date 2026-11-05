import normalFontCss from '@fontsource-variable/plus-jakarta-sans/wght.css?inline';
import italicFontCss from '@fontsource-variable/plus-jakarta-sans/wght-italic.css?inline';
import { toast } from 'sonner';

// Standalone print windows do not inherit the application's stylesheets.
export const printFontCss = `${normalFontCss}\n${italicFontCss}`;

export async function printWithFonts(target: Window = window): Promise<void> {
    try {
        const faces = await Promise.all([
            target.document.fonts.load('400 14px "Plus Jakarta Sans Variable"'),
            target.document.fonts.load('600 14px "Plus Jakarta Sans Variable"'),
            target.document.fonts.load('700 14px "Plus Jakarta Sans Variable"'),
            target.document.fonts.load('italic 400 14px "Plus Jakarta Sans Variable"'),
        ]);
        if (faces.some((loaded) => loaded.length === 0)) {
            throw new Error('The print font is unavailable.');
        }
        await target.document.fonts.ready;
        await Promise.all(Array.from(target.document.images ?? []).map(async (image) => {
            if (typeof image.decode === 'function') await image.decode();
        }));

        if (!target.closed) {
            target.focus();
            target.print();
        }
    } catch {
        toast.error('Font atau gambar dokumen belum dapat dimuat. Muat ulang halaman, lalu coba cetak kembali.');
    }
}
