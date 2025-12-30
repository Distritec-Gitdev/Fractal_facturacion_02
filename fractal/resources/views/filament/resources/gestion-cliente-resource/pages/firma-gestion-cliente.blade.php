{{-- resources/views/filament/resources/gestion-cliente-resource/pages/firma-gestion-cliente.blade.php --}}
<x-filament::page>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">
            Firma del cliente: <span class="font-bold">{{ $this->record->cedula }}</span>
        </h2>
    </x-slot>

    <div class="mt-6 space-y-6">
        {{-- Lienzo para la firma --}}
        <div class="border rounded overflow-hidden">
            <canvas
                id="signature-pad"
                class="w-full h-64"
                style="touch-action: none; cursor: crosshair;"
            ></canvas>
        </div>

        {{-- Controles --}}
        <div class="flex space-x-2">
            <x-filament::button color="secondary" type="button" id="clear-signature">
                Limpiar
            </x-filament::button>
            <x-filament::button wire:click="saveSignature">
                Guardar firma
            </x-filament::button>
        </div>
    </div>

    {{-- Librerías externas --}}
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
    <script src="https://unpkg.com/fit-curves@1.0.5/dist/fit-curves.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/stackblur-canvas/2.2.0/stackblur.min.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const canvas = document.getElementById('signature-pad');
        if (!canvas) {
            console.error('Canvas de firma no encontrado');
            return;
        }
        const ctx = canvas.getContext('2d');

        // 1) Kalman filter para entrada de puntero
        class KalmanFilter {
            constructor(r=0.01, q=3) {
                this.R=r; this.Q=q; this.A=1; this.C=1;
                this.cov=NaN; this.x=NaN;
            }
            filter(z) {
                if (isNaN(this.x)) {
                    this.x = z / this.C;
                    this.cov = this.Q / (this.C*this.C);
                } else {
                    const predX = this.A*this.x;
                    const predCov = this.A*this.cov*this.A + this.R;
                    const K = predCov*this.C/(this.C*predCov*this.C + this.Q);
                    this.x = predX + K*(z - this.C*predX);
                    this.cov = predCov - K*this.C*predCov;
                }
                return this.x;
            }
        }
        const kx = new KalmanFilter(), ky = new KalmanFilter();

        // 2) Init SignaturePad
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: '#fff',
            penColor: '#000',
            minWidth: 0.3, maxWidth: 1.0,
            velocityFilterWeight: 0.9,
            throttle: 16,
            minDistance: 2,
        });

        // Inyectar Kalman en pointer events
        canvas.addEventListener('pointerdown', e => {
            canvas.setPointerCapture(e.pointerId);
        });
        canvas.addEventListener('pointermove', e => {
            if (signaturePad._mouseButtonDown || e.pressure) {
                const x = kx.filter(e.offsetX), y = ky.filter(e.offsetY);
                signaturePad._strokeUpdate({ x, y, pressure: 0.5, velocity: 0 });
            }
        });

        // 3) Retina / DPI
        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio||1, 1);
            canvas.width  = canvas.offsetWidth*ratio;
            canvas.height = canvas.offsetHeight*ratio;
            ctx.scale(ratio, ratio);
            signaturePad.clear();
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // 4) Algoritmos de post‑procesado
        function simplify(points, eps=2) {
            if (points.length<3) return points;
            const sq=(a,b)=>(a.x-b.x)**2+(a.y-b.y)**2;
            const distSeg=(p,a,b)=>{
                const l2=sq(a,b);
                if(!l2) return Math.sqrt(sq(p,a));
                let t=((p.x-a.x)*(b.x-a.x)+(p.y-a.y)*(b.y-a.y))/l2;
                t=Math.max(0,Math.min(1,t));
                const proj={ x: a.x+t*(b.x-a.x), y: a.y+t*(b.y-a.y) };
                return Math.sqrt(sq(p,proj));
            };
            function rdp(pts) {
                let dmax=0, idx=0;
                for(let i=1;i<pts.length-1;i++){
                    const d=distSeg(pts[i], pts[0], pts[pts.length-1]);
                    if(d>dmax){ dmax=d; idx=i; }
                }
                if(dmax>eps){
                    const L=rdp(pts.slice(0,idx+1)), R=rdp(pts.slice(idx));
                    return [...L.slice(0,-1),...R];
                }
                return [pts[0], pts[pts.length-1]];
            }
            return rdp(points);
        }
        function catmullRom(pts, seg=12) {
            const curve=[];
            for(let i=0;i<pts.length-1;i++){
                const p0=pts[i>0?i-1:i],
                      p1=pts[i],
                      p2=pts[i+1],
                      p3=pts[i+2<pts.length?i+2:pts.length-1];
                for(let t=0;t<1;t+=1/seg){
                    const t2=t*t, t3=t2*t;
                    const x=0.5*((-p0.x+3*p1.x-3*p2.x+p3.x)*t3 + (2*p0.x-5*p1.x+4*p2.x-p3.x)*t2 +(-p0.x+p2.x)*t +2*p1.x);
                    const y=0.5*((-p0.y+3*p1.y-3*p2.y+p3.y)*t3 + (2*p0.y-5*p1.y+4*p2.y-p3.y)*t2 +(-p0.y+p2.y)*t +2*p1.y);
                    curve.push({x,y});
                }
            }
            return curve;
        }
        function savgol(points) {
            const coeff=[-3,12,17,12,-3].map(v=>v/35);
            return points.map((p,i,arr)=>{
                let sx=0, sy=0;
                coeff.forEach((c,k)=> {
                    const idx=Math.min(arr.length-1, Math.max(0, i+k-2));
                    sx+=arr[idx].x*c; sy+=arr[idx].y*c;
                });
                return {x:sx, y:sy};
            });
        }

        // 5) Bézier fitting (fit‑curves)
        const fitCurves = window.fitCurves.default || window.fitCurves;

        signaturePad.onEnd = () => {
            const data = signaturePad.toData();
            signaturePad.clear();
            ctx.lineCap='round'; ctx.lineJoin='round';

            data.forEach(stroke => {
                let pts = stroke.points;
                pts = simplify(pts,2);
                pts = catmullRom(pts,12);
                pts = savgol(pts);
                // Bézier
                const beziers = fitCurves(pts, 10);
                ctx.beginPath();
                beziers.forEach(([p0,c1,c2,p1])=>{
                    ctx.moveTo(p0.x,p0.y);
                    ctx.bezierCurveTo(c1.x,c1.y,c2.x,c2.y,p1.x,p1.y);
                });
                ctx.stroke();
                // Desenfoque final
                stackBlurCanvasRGBA(canvas, 0,0,canvas.width,canvas.height, 1);
            });
        };

        // Limpiar
        document.getElementById('clear-signature').onclick = () => signaturePad.clear();

        // Livewire hook
        Livewire.hook('message.sent', (msg, comp) => {
            if (msg.updateQueue.some(i=>i.payload.method==='saveSignature')) {
                if (signaturePad.isEmpty()) {
                    alert('Firma vacía.');
                    msg.cancel();
                    return;
                }
                comp.set('signatureDataUrl', signaturePad.toDataURL('image/png'));
            }
        });
    });
    </script>
</x-filament::page>
