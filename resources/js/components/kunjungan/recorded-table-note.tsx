import { useEffect, useId, useRef, useState } from 'react';
import { optionalAnswers } from '@/lib/optional-answers';

export function RecordedTableNote({ parts, emptyLabel = optionalAnswers.notRecorded }: { parts: (string | null | undefined)[]; emptyLabel?: string }) {
    const text = parts.flatMap(part => part?.trim() ? [part.trim()] : []).join('\n');
    const textId = useId();
    const textRef = useRef<HTMLParagraphElement>(null);
    const [canExpand, setCanExpand] = useState(false);
    const [expanded, setExpanded] = useState(false);

    useEffect(() => {
        const paragraph = textRef.current;
        if (!paragraph) return;

        const measure = () => {
            if (paragraph.clientWidth === 0) return;
            const lineHeight = Number.parseFloat(window.getComputedStyle(paragraph).lineHeight);
            const overflows = paragraph.scrollHeight > Math.ceil(lineHeight * 2);
            setCanExpand(overflows);
            if (!overflows) setExpanded(false);
        };

        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(paragraph);
        let active = true;
        void document.fonts?.ready.then(() => { if (active) measure(); });

        return () => {
            active = false;
            observer.disconnect();
        };
    }, [text]);

    if (!text) return emptyLabel;

    return <div className="min-w-0 [overflow-wrap:anywhere]">
        <p className={`whitespace-pre-wrap text-sm leading-5 text-neutral-700 ${canExpand && !expanded ? 'line-clamp-2 print:line-clamp-none' : ''}`} id={textId} ref={textRef}>{text}</p>
        {canExpand && <button
            aria-controls={textId}
            aria-expanded={expanded}
            className="mt-1 rounded-sm text-xs font-medium text-neutral-700 underline-offset-2 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 print:hidden"
            onClick={() => setExpanded(value => !value)}
            type="button"
        >{expanded ? 'Tutup catatan' : 'Lihat catatan lengkap'}</button>}
    </div>;
}
