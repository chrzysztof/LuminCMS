import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { FieldDefinition } from '@/types/cms';

type MapValue = { lat: number; lng: number; zoom: number; address: string };

type Props = {
    field: FieldDefinition;
    value: unknown;
    onChange: (value: unknown) => void;
};

const asValue = (v: unknown): MapValue | null => {
    const o = v as Partial<MapValue> | null;

    return o &&
        typeof o === 'object' &&
        Number.isFinite(Number(o.lat)) &&
        Number.isFinite(Number(o.lng))
        ? {
              lat: Number(o.lat),
              lng: Number(o.lng),
              zoom: Number(o.zoom) || 14,
              address: o.address ?? '',
          }
        : null;
};

// Znacznik z CSS zamiast obrazków (bundler nie musi rozwiązywać ścieżek ikon Leaflet).
const pin = L.divIcon({
    className: '',
    html: '<span style="display:block;width:22px;height:22px;border-radius:50% 50% 50% 0;background:#5b4bdb;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5);transform:rotate(-45deg)"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 22],
});

/** Wybór miejsca na mapie OpenStreetMap: kliknięcie, przeciągnięcie znacznika albo wyszukiwanie adresu. */
export function MapControl({ field, value, onChange }: Props) {
    const current = asValue(value);
    const box = useRef<HTMLDivElement>(null);
    const map = useRef<L.Map | null>(null);
    const marker = useRef<L.Marker | null>(null);
    const latest = useRef({ current, onChange });
    latest.current = { current, onChange };
    const [query, setQuery] = useState('');
    const [status, setStatus] = useState('');
    const height = Number(field.height) || 320;

    // Inicjalizacja mapy raz na montowanie.
    useEffect(() => {
        if (!box.current) return;
        const start = latest.current.current;
        const m = L.map(box.current).setView(
            [
                start?.lat ?? (Number(field.center_lat) || 52.2297),
                start?.lng ?? (Number(field.center_lng) || 21.0122),
            ],
            start?.zoom ?? (Number(field.zoom) || 14),
        );
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution:
                '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(m);

        const place = (lat: number, lng: number, address?: string) => {
            const prev = latest.current.current;
            latest.current.onChange({
                lat: Math.round(lat * 1e6) / 1e6,
                lng: Math.round(lng * 1e6) / 1e6,
                zoom: m.getZoom(),
                address: address ?? prev?.address ?? '',
            });
        };

        m.on('click', (e: L.LeafletMouseEvent) =>
            place(e.latlng.lat, e.latlng.lng, ''),
        );
        m.on('zoomend', () => {
            const c = latest.current.current;
            if (c && c.zoom !== m.getZoom())
                latest.current.onChange({ ...c, zoom: m.getZoom() });
        });
        map.current = m;
        // Kontener bywa jeszcze bez wymiarów przy pierwszym renderze (zwinięte wiersze, układ).
        setTimeout(() => m.invalidateSize(), 100);

        return () => {
            m.remove();
            map.current = null;
            marker.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Znacznik podąża za wartością.
    useEffect(() => {
        const m = map.current;
        if (!m) return;

        if (!current) {
            marker.current?.remove();
            marker.current = null;

            return;
        }

        if (!marker.current) {
            marker.current = L.marker([current.lat, current.lng], {
                draggable: true,
                icon: pin,
            }).addTo(m);
            marker.current.on('dragend', () => {
                const p = marker.current!.getLatLng();
                latest.current.onChange({
                    lat: Math.round(p.lat * 1e6) / 1e6,
                    lng: Math.round(p.lng * 1e6) / 1e6,
                    zoom: m.getZoom(),
                    address: '',
                });
            });
        } else {
            marker.current.setLatLng([current.lat, current.lng]);
        }
    }, [current?.lat, current?.lng]); // eslint-disable-line react-hooks/exhaustive-deps

    const search = async () => {
        const q = query.trim();
        if (!q) return;
        setStatus('Szukam…');
        try {
            const res = await fetch(
                `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(q)}`,
                {
                    headers: { Accept: 'application/json' },
                },
            );
            const [hit] = (await res.json()) as {
                lat: string;
                lon: string;
                display_name: string;
            }[];

            if (!hit) {
                setStatus('Nie znaleziono adresu.');

                return;
            }

            const lat = Number(hit.lat);
            const lng = Number(hit.lon);
            map.current?.setView(
                [lat, lng],
                Math.max(map.current.getZoom(), 16),
            );
            onChange({
                lat: Math.round(lat * 1e6) / 1e6,
                lng: Math.round(lng * 1e6) / 1e6,
                zoom: map.current?.getZoom() ?? 16,
                address: hit.display_name.slice(0, 255),
            });
            setStatus('');
        } catch {
            setStatus(
                'Wyszukiwanie jest niedostępne. Kliknij na mapie, aby wskazać miejsce.',
            );
        }
    };

    return (
        <div className="space-y-2">
            <div className="flex gap-2">
                <Input
                    value={query}
                    placeholder="Wyszukaj adres…"
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            void search();
                        }
                    }}
                />
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => void search()}
                >
                    <Search /> Szukaj
                </Button>
            </div>
            {status && (
                <p className="text-xs text-muted-foreground">{status}</p>
            )}
            <div
                ref={box}
                style={{ height }}
                className="z-0 w-full overflow-hidden rounded-md border"
            />
            <div className="flex flex-wrap items-center gap-3 text-xs text-muted-foreground">
                {current ? (
                    <>
                        <span className="tabular-nums">
                            {current.lat.toFixed(5)}, {current.lng.toFixed(5)}
                        </span>
                        {current.address && (
                            <span className="truncate">{current.address}</span>
                        )}
                        <button
                            type="button"
                            className="inline-flex items-center gap-1 text-destructive"
                            onClick={() => onChange(null)}
                        >
                            <X className="size-3.5" /> Usuń miejsce
                        </button>
                    </>
                ) : (
                    <span>Kliknij na mapie, aby wskazać miejsce.</span>
                )}
            </div>
        </div>
    );
}
