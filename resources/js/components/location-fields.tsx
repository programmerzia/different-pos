import { AlertCircle, ChevronDown, X } from "lucide-react";
import {
    useEffect,
    useId,
    useMemo,
    useRef,
    useState,
    type ReactNode,
} from "react";
import { Input } from "@/components/ui/input";
import { useDigits } from "@/hooks/use-digits";
import { useT } from "@/i18n";
import {
    geoName,
    loadGeoLevel,
    loadPostCodes,
    searchGeo,
    type GeoChain,
    type GeoIds,
    type GeoLevel,
    type GeoOption,
    type GeoPlace,
    type GeoPostCode,
    type GeoSearchHit,
} from "@/lib/geo";
import { cn } from "@/lib/utils";

export type LocationValue = GeoIds & { post_code: string };

type Level = "division" | "district" | "upazila" | "union";

const LEVELS: { key: Level; level: GeoLevel; id: keyof GeoIds }[] = [
    { key: "division", level: "divisions", id: "geo_division_id" },
    { key: "district", level: "districts", id: "geo_district_id" },
    { key: "upazila", level: "upazilas", id: "geo_upazila_id" },
    { key: "union", level: "unions", id: "geo_union_id" },
];

/**
 * Cascading Division → District → Upazila/Thana → (Union) + Post code (02_AppFlow §5.2): every level
 * is a type-ahead combobox (EN or BN, old spellings too). Typing searches that level under the chosen
 * parent (all of it when no parent is chosen); picking a deeper place fills its parents; changing a
 * parent clears the children that no longer fit. The post code suggests the offices of the upazila.
 * Keyboard: ↓/↑ move, Enter picks, Esc closes, Tab leaves (keeps the pick), Backspace on a picked
 * value clears it.
 */
export function LocationFields({
    value,
    names,
    onChange,
    errors = {},
    showUnion = false,
    required = {},
    className,
    children,
}: {
    value: LocationValue;
    /** Names of the saved ids (from the server) so an existing record shows text before any fetch. */
    names?: Partial<GeoChain> | null | undefined;
    onChange: (patch: Partial<LocationValue>) => void;
    errors?: Partial<Record<keyof LocationValue, string | undefined>>;
    showUnion?: boolean;
    required?: Partial<Record<Level | "post_code", boolean>>;
    className?: string;
    /** Extra fields rendered in the same grid after the post code (e.g. area). */
    children?: ReactNode;
}) {
    const { t, locale } = useT();
    const digits = useDigits();
    const [known, setKnown] = useState<Record<number, GeoPlace>>(() => {
        const seed: Record<number, GeoPlace> = {};
        for (const place of [
            names?.division,
            names?.district,
            names?.upazila,
            names?.union,
        ]) {
            if (place) {
                seed[place.id] = place;
            }
        }

        return seed;
    });
    const remember = (places: GeoPlace[]) =>
        setKnown((prev) => {
            const next = { ...prev };
            for (const place of places) {
                next[place.id] = place;
            }

            return next;
        });

    const pick = (level: Level, hit: GeoSearchHit | GeoOption | null) => {
        const index = LEVELS.findIndex((l) => l.key === level);
        const patch: Partial<LocationValue> = {};
        // Children of the changed level no longer fit: clear them.
        for (const l of LEVELS.slice(index)) {
            patch[l.id] = null;
        }
        if (hit) {
            patch[LEVELS[index]!.id] = hit.id;
            remember([hit]);
            if ("chain" in hit) {
                const chain = hit.chain;
                const parents: GeoPlace[] = [];
                for (const l of LEVELS.slice(0, index)) {
                    const place = chain[l.key];
                    patch[l.id] = place?.id ?? null;
                    if (place) {
                        parents.push(place);
                    }
                }
                remember(parents);
            }
        }
        onChange(patch);
    };

    const parentOf = (level: Level): number | null => {
        const index = LEVELS.findIndex((l) => l.key === level);
        if (index === 0) {
            return null;
        }

        return value[LEVELS[index - 1]!.id];
    };

    const levels = showUnion ? LEVELS : LEVELS.slice(0, 3);

    return (
        <div
            className={cn(
                "grid grid-cols-1 gap-3 text-xs sm:grid-cols-4",
                className,
            )}
        >
            {levels.map((l) => (
                <GeoCombobox
                    key={l.key}
                    name={l.id}
                    label={t(`location.${l.key}`)}
                    required={required[l.key] ?? false}
                    level={l.level}
                    parent={parentOf(l.key)}
                    selected={
                        value[l.id] !== null
                            ? (known[value[l.id] as number] ?? null)
                            : null
                    }
                    selectedId={value[l.id]}
                    error={errors[l.id]}
                    locale={locale}
                    onPick={(hit) => pick(l.key, hit)}
                    onOptions={remember}
                />
            ))}
            <PostCodeField
                value={value.post_code}
                upazila={value.geo_upazila_id}
                required={required.post_code ?? false}
                error={errors.post_code}
                locale={locale}
                format={digits.n}
                onChange={(post_code) => onChange({ post_code })}
            />
            {children}
        </div>
    );
}

function GeoCombobox({
    name,
    label,
    required,
    level,
    parent,
    selected,
    selectedId,
    error,
    locale,
    onPick,
    onOptions,
}: {
    name: string;
    label: string;
    required: boolean;
    level: GeoLevel;
    parent: number | null;
    selected: GeoPlace | null;
    selectedId: number | null;
    error: string | undefined;
    locale: "en" | "bn";
    onPick: (hit: GeoSearchHit | GeoOption | null) => void;
    onOptions: (places: GeoPlace[]) => void;
}) {
    const { t } = useT();
    const id = useId();
    const listId = `${id}-list`;
    const [text, setText] = useState("");
    const [editing, setEditing] = useState(false);
    const [open, setOpen] = useState(false);
    const [options, setOptions] = useState<(GeoOption | GeoSearchHit)[]>([]);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const shown = editing ? text : geoName(selected, locale);

    // The selected id may be known before its name arrives (edit form): fetch the level once to label it.
    useEffect(() => {
        if (selectedId === null || selected !== null) {
            return;
        }
        let cancelled = false;
        loadGeoLevel(level, parent)
            .then((rows) => {
                if (!cancelled) {
                    onOptions(rows);
                }
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- only when the selection has no name yet
    }, [selectedId, selected === null, level, parent]);

    useEffect(() => {
        if (!open) {
            return;
        }
        if (timer.current) {
            clearTimeout(timer.current);
        }
        const q = text.trim();
        const controller = new AbortController();
        setLoading(true);
        timer.current = setTimeout(
            () => {
                const request =
                    q.length > 0
                        ? searchGeo(q, level, parent, controller.signal)
                        : loadGeoLevel(level, parent);
                request
                    .then((rows) => {
                        if (controller.signal.aborted) {
                            return;
                        }
                        setOptions(rows);
                        onOptions(rows);
                        setActive(0);
                        setLoading(false);
                    })
                    .catch(() => {
                        if (!controller.signal.aborted) {
                            setOptions([]);
                            setLoading(false);
                        }
                    });
            },
            q.length > 0 ? 150 : 0,
        );

        return () => {
            controller.abort();
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps -- onOptions is stable enough; re-run on text/open/parent
    }, [text, open, level, parent]);

    const startEditing = (initial: string) => {
        setEditing(true);
        setText(initial);
        setOpen(true);
    };

    const choose = (option: GeoOption | GeoSearchHit | undefined) => {
        if (!option) {
            return;
        }
        onPick(option);
        setEditing(false);
        setText("");
        setOpen(false);
    };

    const commitOrRevert = () => {
        if (editing && text.trim() === "" && selected) {
            onPick(null);
        }
        setEditing(false);
        setText("");
        setOpen(false);
    };

    const onKeyDown = (event: React.KeyboardEvent<HTMLInputElement>) => {
        if (event.key === "ArrowDown") {
            event.preventDefault();
            if (!open) {
                startEditing(editing ? text : "");
            } else {
                setActive((i) => Math.min(options.length - 1, i + 1));
            }
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            setActive((i) => Math.max(0, i - 1));
        } else if (event.key === "Enter" && open) {
            event.preventDefault();
            choose(options[active]);
        } else if (event.key === "Escape" && open) {
            event.stopPropagation();
            event.preventDefault();
            setEditing(false);
            setText("");
            setOpen(false);
        } else if (event.key === "Backspace" && !editing && selected !== null) {
            event.preventDefault();
            onPick(null);
            startEditing("");
        }
    };

    const describedBy = error ? `${id}-error` : undefined;
    const activeId =
        open && options[active] ? `${listId}-${options[active].id}` : undefined;

    return (
        <div className="relative">
            <label
                htmlFor={id}
                className="text-[11px] font-semibold text-muted-foreground"
            >
                {label}
                {required ? " *" : null}
            </label>
            <div
                className={cn(
                    "relative mt-1",
                    error && "[&_input]:bg-danger-soft",
                )}
            >
                <Input
                    id={id}
                    name={name}
                    // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role -- WAI-ARIA type-ahead combobox pattern
                    role="combobox"
                    aria-expanded={open}
                    aria-controls={open ? listId : undefined}
                    aria-autocomplete="list"
                    aria-activedescendant={activeId}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    autoComplete="off"
                    required={required}
                    lang={locale}
                    className="pr-7 font-semibold"
                    placeholder={t("location.search_ph")}
                    value={shown}
                    onChange={(e) => {
                        setEditing(true);
                        setText(e.target.value);
                        setOpen(true);
                    }}
                    onFocus={() => {
                        if (!selected) {
                            startEditing("");
                        }
                    }}
                    onClick={() => {
                        if (!open) {
                            startEditing(editing ? text : "");
                        }
                    }}
                    onBlur={() => {
                        // Let a click on an option land first.
                        window.setTimeout(commitOrRevert, 120);
                    }}
                    onKeyDown={onKeyDown}
                />
                {selected && !editing ? (
                    <button
                        type="button"
                        tabIndex={-1}
                        aria-label={t("location.clear", { field: label })}
                        className="absolute top-1/2 right-1.5 flex size-5 -translate-y-1/2 items-center justify-center rounded text-muted-foreground hover:text-foreground"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => onPick(null)}
                    >
                        <X className="size-3" aria-hidden="true" />
                    </button>
                ) : (
                    <ChevronDown
                        className="pointer-events-none absolute top-1/2 right-2 size-3 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                )}
                {open ? (
                    <ul
                        id={listId}
                        // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role, jsx-a11y/no-noninteractive-element-to-interactive-role -- ARIA listbox popup of the combobox
                        role="listbox"
                        aria-label={label}
                        className="absolute z-30 mt-1 max-h-60 w-full min-w-48 overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
                    >
                        {loading && options.length === 0 ? (
                            <li className="px-2 py-1.5 text-xs text-muted-foreground">
                                {t("location.loading")}
                            </li>
                        ) : null}
                        {!loading && options.length === 0 ? (
                            <li className="px-2 py-1.5 text-xs text-muted-foreground">
                                {t("location.none")}
                            </li>
                        ) : null}
                        {options.map((option, index) => {
                            const chain =
                                "chain" in option ? option.chain : null;
                            const parentText = chain
                                ? [
                                      level === "unions"
                                          ? geoName(chain.upazila, locale)
                                          : null,
                                      level !== "districts" &&
                                      level !== "divisions"
                                          ? geoName(chain.district, locale)
                                          : null,
                                      level === "districts"
                                          ? geoName(chain.division, locale)
                                          : null,
                                  ]
                                      .filter(Boolean)
                                      .join(", ")
                                : (option.city_name_en ?? "");

                            return (
                                // oxlint-disable-next-line jsx-a11y/click-events-have-key-events -- keyboard handled by the combobox input (aria-activedescendant)
                                <li
                                    key={option.id}
                                    id={`${listId}-${option.id}`}
                                    // oxlint-disable-next-line jsx-a11y/prefer-tag-over-role, jsx-a11y/no-noninteractive-element-to-interactive-role -- ARIA option; focus stays on the input
                                    role="option"
                                    aria-selected={index === active}
                                    className={cn(
                                        "flex min-h-11 cursor-pointer flex-col justify-center rounded-sm px-2 py-1 text-xs md:min-h-8",
                                        index === active && "bg-accent",
                                    )}
                                    onMouseEnter={() => setActive(index)}
                                    onMouseDown={(e) => e.preventDefault()}
                                    onClick={() => choose(option)}
                                >
                                    <span className="font-medium">
                                        {geoName(option, locale)}
                                        {locale === "en" && option.name_bn ? (
                                            <span
                                                lang="bn"
                                                className="ml-1.5 font-normal text-muted-foreground"
                                            >
                                                {option.name_bn}
                                            </span>
                                        ) : null}
                                    </span>
                                    {parentText ? (
                                        <span className="text-[10px] text-muted-foreground">
                                            {parentText}
                                        </span>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ul>
                ) : null}
            </div>
            <output className="sr-only">
                {open && options.length > 0
                    ? t("location.results", { count: options.length })
                    : ""}
            </output>
            {error ? (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="mt-1 flex items-center gap-1 text-[10px] font-semibold text-destructive"
                >
                    <AlertCircle className="size-2.75" aria-hidden="true" />
                    <span>{error}</span>
                </p>
            ) : null}
        </div>
    );
}

function PostCodeField({
    value,
    upazila,
    required,
    error,
    locale,
    format,
    onChange,
}: {
    value: string;
    upazila: number | null;
    required: boolean;
    error: string | undefined;
    locale: "en" | "bn";
    format: (value: string) => string;
    onChange: (value: string) => void;
}) {
    const { t } = useT();
    const id = useId();
    const [loaded, setLoaded] = useState<{
        upazila: number;
        rows: GeoPostCode[];
    } | null>(null);
    const codes = useMemo(
        () => (loaded && loaded.upazila === upazila ? loaded.rows : []),
        [loaded, upazila],
    );

    useEffect(() => {
        if (upazila === null) {
            return;
        }
        let cancelled = false;
        loadPostCodes(upazila)
            .then((rows) => {
                if (!cancelled) {
                    setLoaded({ upazila, rows });
                }
            })
            .catch(() => undefined);

        return () => {
            cancelled = true;
        };
    }, [upazila]);

    const suggestions = useMemo(() => {
        const seen = new Set<string>();

        return codes.filter((c) => {
            if (seen.has(c.post_code)) {
                return false;
            }
            seen.add(c.post_code);

            return true;
        });
    }, [codes]);

    return (
        <div>
            <label
                htmlFor={id}
                className="text-[11px] font-semibold text-muted-foreground"
            >
                {t("location.post_code")}
                {required ? " *" : null}
            </label>
            <div className={cn("mt-1", error && "[&_input]:bg-danger-soft")}>
                <Input
                    id={id}
                    name="post_code"
                    inputMode="numeric"
                    maxLength={4}
                    list={suggestions.length > 0 ? `${id}-codes` : undefined}
                    className="font-mono"
                    placeholder={t("location.post_code_ph")}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={error ? `${id}-error` : undefined}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
                {suggestions.length > 0 ? (
                    <datalist id={`${id}-codes`}>
                        {suggestions.map((c) => (
                            <option key={c.id} value={c.post_code}>
                                {format(c.post_code)} ·{" "}
                                {locale === "bn" && c.post_office_bn
                                    ? c.post_office_bn
                                    : c.post_office_en}
                            </option>
                        ))}
                    </datalist>
                ) : null}
            </div>
            {error ? (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="mt-1 flex items-center gap-1 text-[10px] font-semibold text-destructive"
                >
                    <AlertCircle className="size-2.75" aria-hidden="true" />
                    <span>{error}</span>
                </p>
            ) : null}
        </div>
    );
}
