import { useId } from 'react';
import { bodyLocations, organLocations, type BodyView, type BodyLocation } from '@/lib/body-examination';

export { organLocations } from '@/lib/body-examination';

interface Props { mode: 'organs' | 'body'; view: BodyView; model: 'male' | 'female'; selectedId: string; highlightedIds?: string[]; disabled?: boolean; onSelect: (location: BodyLocation) => void; }

export function HumanBodyModel({ mode, view, model, selectedId, highlightedIds = [], disabled = false, onSelect }: Props) {
    const id = useId().replace(/:/g, '');
    const back = view === 'back';
    const female = model === 'female';
    const arm = 'M133 145 C116 143 110 160 109 178 L101 223 Q97 241 100 254 L88 303 L77 353 L73 373 L63 383 L55 401 Q52 409 57 408 L65 397 L60 419 Q60 425 64 423 L71 405 L68 425 Q70 430 74 425 L79 407 L78 423 Q80 428 83 423 L87 405 L87 415 Q90 419 92 413 L94 388 L92 371 L102 339 Q115 309 116 284 L122 258 Q133 234 136 209 L149 169Z';
    const leg = 'M145 341 C137 365 140 403 144 427 L148 474 Q141 493 145 512 C136 542 143 578 150 606 L153 648 L151 677 Q142 687 138 701 Q136 713 144 714 L178 714 Q184 710 181 701 L176 675 L176 648 L180 590 C188 555 184 534 181 512 L185 481 L192 423 Q197 396 200 384 L199 348Z';
    const torso = female ? 'M181 123 Q164 135 135 145 Q125 155 136 181 Q145 202 153 223 Q164 250 158 275 Q150 297 144 326 L145 354 Q173 381 200 384 Q227 381 255 354 L256 326 Q250 297 242 275 Q236 250 247 223 Q255 202 264 181 Q275 155 265 145 Q236 135 219 123Z' : 'M181 123 Q161 135 133 145 Q123 155 137 187 L148 221 Q157 247 155 275 L147 319 L145 354 Q171 380 200 384 Q229 380 255 354 L253 319 Q245 288 245 275 Q243 247 252 221 L263 187 Q277 155 267 145 Q239 135 219 123Z';
    return (
        <svg aria-label={mode === 'organs' ? 'Peta organ tubuh interaktif tampak depan' : 'Peta seluruh tubuh interaktif tampak ' + (back ? 'belakang' : 'depan')} className="mx-auto h-auto max-h-[680px] w-full max-w-[380px]" viewBox="0 0 400 750" role="group">
            <defs>
                <linearGradient id={id + '-skin'} x1="0" x2="1"><stop stopColor="#e7cbb4"/><stop offset="0.5" stopColor="#fff1e4"/><stop offset="1" stopColor="#dcc0aa"/></linearGradient>
                <clipPath id={id + '-shape'}><path d={torso}/><path d={arm}/><path d={arm} transform="translate(400 0) scale(-1 1)"/><path d={leg}/><path d={leg} transform="translate(400 0) scale(-1 1)"/><path d="M173 67 C168 39 180 27 200 27 C220 27 232 39 227 67 L227 87 Q221 109 212 115 L213 126 L221 133 Q200 147 179 133 L187 126 L188 115 Q179 109 173 87Z"/></clipPath>
            </defs>
            <text x="58" y="23" textAnchor="middle" fill="currentColor" fontSize="12">{back ? 'Kiri pasien' : 'Kanan pasien'}</text>
            <text x="342" y="23" textAnchor="middle" fill="currentColor" fontSize="12">{back ? 'Kanan pasien' : 'Kiri pasien'}</text>
            <g fill={'url(#' + id + '-skin)'} stroke="#876c5e" strokeWidth="1.5" strokeLinejoin="round">
                <path d={arm}/><path d={arm} transform="translate(400 0) scale(-1 1)"/>
                <path d={leg}/><path d={leg} transform="translate(400 0) scale(-1 1)"/>
                <path d={torso}/>
                <path d="M173 67 C168 39 180 27 200 27 C220 27 232 39 227 67 L227 87 Q221 109 212 115 L213 126 L221 133 Q200 147 179 133 L187 126 L188 115 Q179 109 173 87Z"/>
                <path d="M173 70 Q166 65 169 80 L175 89 M227 70 Q234 65 231 80 L225 89"/>
            </g>
            <g fill="none" stroke="#a17f6b" strokeWidth="1.1" strokeLinecap="round" opacity="0.8" pointerEvents="none">
                {back ? <>
                    <path d="M200 125 L200 323 M182 142 Q174 172 157 185 L168 219 Q190 209 192 174 M218 142 Q226 172 243 185 L232 219 Q210 209 208 174 M158 228 Q178 253 181 291 M242 228 Q222 253 219 291 M166 295 Q185 318 200 325 Q215 318 234 295 M200 331 L200 373 M151 351 Q173 381 193 359 M249 351 Q227 381 207 359"/>
                    <path d="M155 393 Q165 435 161 469 M245 393 Q235 435 239 469 M150 490 Q164 501 180 490 M220 490 Q236 501 250 490 M155 521 Q142 560 163 591 M245 521 Q258 560 237 591 M166 594 L165 671 M234 594 L235 671"/>
                    <path d="M181 102 Q200 96 219 102 M190 112 L191 132 M210 112 L209 132"/>
                </> : <>
                    <path d="M180 77 Q187 73 194 77 M206 77 Q213 73 220 77 M183 82 Q188 85 193 82 M207 82 Q212 85 217 82 M200 82 L197 94 L202 95 M192 103 Q200 106 208 103 M187 119 L194 136 M213 119 L206 136 M200 148 L200 221 M146 154 Q169 149 194 150 M206 150 Q231 149 254 154"/>
                    {female ? <path d="M147 184 Q150 217 174 211 Q192 207 193 187 M207 187 Q208 207 226 211 Q250 217 253 184 M159 233 Q174 246 172 270 M241 233 Q226 246 228 270"/> : <path d="M144 175 Q155 207 193 198 M207 198 Q245 207 256 175 M176 211 Q200 219 224 211 M177 234 Q200 241 223 234 M181 258 Q200 264 219 258 M184 279 Q200 285 216 279 M200 218 L200 297"/>}
                    <path d="M198 299 Q200 303 202 299 M153 323 Q173 332 190 351 M247 323 Q227 332 210 351 M190 360 Q200 367 210 360 M156 388 Q177 431 164 473 M244 388 Q223 431 236 473 M153 485 Q165 475 177 485 L174 504 Q164 510 155 502Z M223 485 Q235 475 247 485 L245 502 Q236 510 226 504Z M155 520 L162 645 M245 520 L238 645"/>
                </>}
                <path d="M126 161 Q113 181 119 216 M274 161 Q287 181 281 216 M112 224 L108 249 M288 224 L292 249 M104 277 Q96 308 88 346 M296 277 Q304 308 312 346 M78 376 L89 383 M322 376 L311 383 M68 392 L84 398 M332 392 L316 398 M151 680 L174 680 M226 680 L249 680 M144 702 L147 712 M152 701 L154 713 M161 701 L163 713 M169 701 L171 712 M256 702 L253 712 M248 701 L246 713 M239 701 L237 713 M231 701 L229 712"/>
            </g>
            {mode === 'organs' ? organLocations.map((location) => <g key={location.id} aria-label={location.label} aria-pressed={selectedId === location.id} aria-disabled={disabled} role="button" tabIndex={disabled ? -1 : 0} onClick={() => !disabled && onSelect(location)} onKeyDown={(event) => { if (!disabled && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onSelect(location); } }} className="cursor-pointer outline-none focus-visible:stroke-blue-600 hover:brightness-110" fill={location.color} fillOpacity={selectedId === location.id || highlightedIds.includes(location.id) ? 0.95 : 0.55} stroke={selectedId === location.id ? '#1d4ed8' : highlightedIds.includes(location.id) ? '#0f766e' : '#735f58'} strokeWidth={selectedId === location.id ? 2.8 : 1.2}>
                <title>{location.label}</title><path d={location.path}/>
            </g>) : <g clipPath={'url(#' + id + '-shape)'}>{bodyLocations(view).map((location) => <rect key={location.id} x={location.x} y={location.y} width={location.width} height={location.height} rx="5" fill="currentColor" fillOpacity={selectedId === location.id ? 0.4 : highlightedIds.includes(location.id) ? 0.2 : 0} stroke={selectedId === location.id ? 'currentColor' : 'none'} className="cursor-pointer text-blue-600 outline-none hover:fill-blue-500 hover:fill-opacity-20 focus-visible:fill-blue-500 focus-visible:fill-opacity-25 focus-visible:stroke-blue-600" aria-label={location.label + ' (' + (back ? 'belakang' : 'depan') + ')'} aria-pressed={selectedId === location.id} aria-disabled={disabled} role="button" tabIndex={disabled ? -1 : 0} onClick={() => !disabled && onSelect(location)} onKeyDown={(event) => { if (!disabled && ['Enter', ' '].includes(event.key)) { event.preventDefault(); onSelect(location); } }}><title>{location.label}</title></rect>)}</g>}
            <text x="200" y="741" textAnchor="middle" fill="currentColor" fontSize="12">{back ? 'Tampak belakang' : 'Tampak depan'} · {female ? 'Model perempuan' : 'Model laki-laki'}</text>
        </svg>
    );
}
