import { UploadCloud, X } from 'lucide-react';
import { useId, useRef, useState, type DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/** Keyboard-accessible file picker + drop zone with client-side type/size checks (the server re-validates). */
export function Upload({
    onFiles,
    accept,
    maxSizeMb = 5,
    multiple = false,
    label,
}: {
    onFiles: (files: File[]) => void;
    accept?: string;
    maxSizeMb?: number;
    multiple?: boolean;
    label?: string;
}) {
    const { t } = useT();
    const input = useRef<HTMLInputElement>(null);
    const [error, setError] = useState<string | null>(null);
    const [over, setOver] = useState(false);
    const [names, setNames] = useState<string[]>([]);
    const errorId = useId();

    const accepts = (file: File): boolean => {
        if (!accept) {
            return true;
        }

        return accept.split(',').some((rule) => {
            const r = rule.trim().toLowerCase();

            return r.startsWith('.')
                ? file.name.toLowerCase().endsWith(r)
                : r.endsWith('/*')
                  ? file.type.startsWith(r.slice(0, -1))
                  : file.type === r;
        });
    };

    const take = (list: FileList | File[]) => {
        const files = [...list];
        const bad = files.find((f) => !accepts(f));
        const big = files.find((f) => f.size > maxSizeMb * 1024 * 1024);
        if (bad) {
            setError(t('upload.bad_type', { name: bad.name }));

            return;
        }
        if (big) {
            setError(t('upload.too_big', { name: big.name, mb: maxSizeMb }));

            return;
        }
        setError(null);
        const chosen = multiple ? files : files.slice(0, 1);
        setNames(chosen.map((f) => f.name));
        onFiles(chosen);
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        setOver(false);
        take(event.dataTransfer.files);
    };

    return (
        <div>
            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setOver(true);
                }}
                onDragLeave={() => setOver(false)}
                onDrop={onDrop}
                className={cn(
                    'flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center',
                    over ? 'border-primary bg-primary-soft' : 'bg-muted',
                )}
            >
                <UploadCloud
                    className="size-6 text-muted-foreground"
                    aria-hidden="true"
                />
                <p className="text-sm text-muted-foreground">
                    {label ?? t('upload.drop')}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    className="min-h-11"
                    onClick={() => input.current?.click()}
                >
                    {t('upload.choose')}
                </Button>
                <input
                    ref={input}
                    type="file"
                    className="sr-only"
                    tabIndex={-1}
                    accept={accept}
                    multiple={multiple}
                    aria-describedby={error ? errorId : undefined}
                    onChange={(e) => e.target.files && take(e.target.files)}
                />
            </div>
            {names.length > 0 ? (
                <ul className="mt-2 text-sm">
                    {names.map((name) => (
                        <li key={name} className="flex items-center gap-2">
                            <span className="truncate">{name}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8"
                                aria-label={t('upload.remove')}
                                onClick={() => {
                                    setNames([]);
                                    onFiles([]);
                                }}
                            >
                                <X aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : null}
            {error ? (
                <p
                    id={errorId}
                    role="alert"
                    className="mt-2 text-sm text-destructive"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
