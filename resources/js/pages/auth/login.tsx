import { Form, Head } from '@inertiajs/react';
import {
    AlertCircle,
    Building2,
    CheckCircle2,
    Eye,
    EyeOff,
    Lock,
    TriangleAlert,
    User,
} from 'lucide-react';
import {
    useEffect,
    useRef,
    useState,
    type KeyboardEvent,
    type ReactNode,
    type RefObject,
} from 'react';
import PasskeyVerify from '@/components/passkey-verify';
import { TenantLogo } from '@/components/tenant-logo';
import TextLink from '@/components/text-link';
import { Spinner } from '@/components/ui/spinner';
import { useT } from '@/i18n';
import { TranslatedAuthLayout } from '@/layouts/auth/translated-auth-layout';
import {
    capsLockOn,
    isThrottleMessage,
    lockUntil,
    needsBusinessCode,
} from '@/lib/login';
import { cn } from '@/lib/utils';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

export type RememberedBusiness = {
    code: string;
    name: string;
    logo_url: string | null;
    tagline: string | null;
};

type Props = {
    status?: string;
    statusKey?: string | null;
    canResetPassword: boolean;
    business: RememberedBusiness | null;
};

type Errors = Partial<Record<'login' | 'password' | 'business', string>>;

/*
 * Visual design ported from the mockup login screen (tools/mockup/baseline/parts/render_shell.py, commit
 * 2078d6c): 36 px sunken inputs with a 14 px leading icon, 12 px semibold labels, 11 px secondary text.
 * Inputs and buttons grow to 44 px below md (touch targets).
 */
const FIELD =
    'w-full min-h-11 rounded-lg border border-border bg-muted pl-8 text-base text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-ring/40 disabled:opacity-50 aria-invalid:border-destructive md:h-9 md:min-h-9 md:text-xs';
const FIELD_ICON =
    'pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground';
const LABEL = 'text-xs font-semibold text-foreground';
const FIELD_ERROR = 'mt-1 text-[11px] font-medium text-danger';

/** Seconds left until `until` (ms timestamp), re-rendering each second; 0 once passed. */
function useCountdown(until: number | null): number {
    const [now, setNow] = useState(() => Date.now());
    useEffect(() => {
        if (until === null) {
            return;
        }
        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, [until]);

    return until === null ? 0 : Math.max(0, Math.ceil((until - now) / 1000));
}

export default function Login({
    status,
    statusKey,
    canResetPassword,
    business,
}: Props) {
    const { t } = useT();
    const [lockedUntil, setLockedUntil] = useState<number | null>(null);
    const summaryRef = useRef<HTMLDivElement>(null);

    // An error answer: start the countdown for a throttle, then move focus to the error summary.
    const onError = (errors: Errors) => {
        setLockedUntil(lockUntil(errors.login, Date.now()));
        window.requestAnimationFrame(() => summaryRef.current?.focus());
    };

    return (
        <>
            <Head title={t('login.title')} />

            {status ? (
                <output className="flex items-start gap-2 rounded-xl border border-success/30 bg-success-soft p-3 text-xs font-semibold text-success">
                    <CheckCircle2
                        className="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>{status}</span>
                </output>
            ) : null}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                onError={onError}
            >
                {({ processing, errors }) => (
                    <LoginFields
                        processing={processing}
                        errors={errors as Errors}
                        business={business}
                        canResetPassword={canResetPassword}
                        statusMessage={statusKey ? t(statusKey) : null}
                        lockedUntil={lockedUntil}
                        summaryRef={summaryRef}
                    />
                )}
            </Form>

            <PasskeyVerify
                label={t('login.passkey')}
                loadingLabel={t('login.passkey_loading')}
                separator={t('login.or_continue')}
                separatorFirst
            />
        </>
    );
}

export function LoginFields({
    processing,
    errors,
    business,
    canResetPassword,
    statusMessage = null,
    lockedUntil = null,
    summaryRef,
}: {
    processing: boolean;
    errors: Errors;
    business: RememberedBusiness | null;
    canResetPassword: boolean;
    statusMessage?: string | null;
    /** When a throttled sign-in may be retried (ms timestamp), from the last error answer. */
    lockedUntil?: number | null;
    summaryRef?: RefObject<HTMLDivElement | null>;
}) {
    const { t } = useT();
    const [login, setLogin] = useState('');
    const [changing, setChanging] = useState(false);
    const [capsLock, setCapsLock] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const businessRef = useRef<HTMLInputElement>(null);
    const loginRef = useRef<HTMLInputElement>(null);
    const passwordRef = useRef<HTMLInputElement>(null);

    const needsBusiness = needsBusinessCode(login);
    const remembered = business !== null && !changing;
    const throttled = isThrottleMessage(errors.login);
    const remaining = useCountdown(lockedUntil);
    const locked = remaining > 0;

    // A value already in the identifier on load (browser autofill): start in the password instead.
    useEffect(() => {
        const timer = window.setTimeout(() => {
            if (
                loginRef.current?.value &&
                document.activeElement === loginRef.current
            ) {
                passwordRef.current?.focus();
            }
        }, 0);

        return () => window.clearTimeout(timer);
    }, []);

    useEffect(() => {
        if (changing) {
            businessRef.current?.focus();
        }
    }, [changing]);

    const fieldErrors = (['login', 'business', 'password'] as const)
        .filter((key) => errors[key] && !(key === 'login' && throttled))
        .map((key) => ({ key, message: errors[key] as string }));
    const hasSummary = throttled || fieldErrors.length > 0 || !!statusMessage;
    const toggleLabel = showPassword
        ? t('password_input.hide')
        : t('password_input.show');

    const trackCaps = (event: KeyboardEvent<HTMLInputElement>) =>
        setCapsLock(capsLockOn(event));

    return (
        <div className="space-y-4">
            {/* Error summary: the mockup's lock-out panel, used for every error answer. */}
            <div
                ref={summaryRef}
                tabIndex={-1}
                role="alert"
                aria-live="assertive"
                className={cn(
                    'rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring/40',
                    hasSummary
                        ? 'flex items-start gap-2 border border-danger/30 bg-danger-soft p-3 text-xs font-semibold text-danger'
                        : 'hidden',
                )}
            >
                {hasSummary ? (
                    <>
                        {throttled ? (
                            <Lock
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                        ) : (
                            <AlertCircle
                                className="mt-0.5 size-4 shrink-0"
                                aria-hidden="true"
                            />
                        )}
                        <div>
                            {throttled ? (
                                <>
                                    <div>{t('login.throttled_title')}</div>
                                    <div className="mt-0.5 text-[11px] font-normal">
                                        {lockedUntil === null
                                            ? errors.login
                                            : locked
                                              ? t('login.throttled')
                                              : t('login.throttle_over')}
                                    </div>
                                </>
                            ) : statusMessage && fieldErrors.length === 0 ? (
                                <div>{statusMessage}</div>
                            ) : (
                                <>
                                    <div>{t('login.error_summary')}</div>
                                    <ul className="mt-0.5 text-[11px] font-normal">
                                        {fieldErrors.map((error) => (
                                            <li key={error.key}>
                                                <a
                                                    href={`#${error.key}`}
                                                    className="underline underline-offset-2"
                                                >
                                                    {error.message}
                                                </a>
                                            </li>
                                        ))}
                                    </ul>
                                </>
                            )}
                        </div>
                    </>
                ) : null}
            </div>

            <div>
                <div>
                    <label htmlFor="login" className={LABEL}>
                        {t('login.identifier')}
                    </label>
                    <div className="relative mt-1">
                        <input
                            ref={loginRef}
                            id="login"
                            name="login"
                            type="text"
                            required
                            autoFocus
                            autoComplete="username"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck={false}
                            enterKeyHint="next"
                            value={login}
                            onChange={(event) => setLogin(event.target.value)}
                            placeholder={t('login.identifier_placeholder')}
                            aria-invalid={
                                errors.login && !throttled ? true : undefined
                            }
                            aria-describedby={
                                errors.login && !throttled
                                    ? 'login-error'
                                    : undefined
                            }
                            className={cn(FIELD, 'pr-3')}
                        />
                        <User className={FIELD_ICON} aria-hidden="true" />
                    </div>
                    {errors.login && !throttled ? (
                        <p id="login-error" className={FIELD_ERROR}>
                            {errors.login}
                        </p>
                    ) : null}
                </div>

                {/* Always rendered so it can grow open without shifting the page; inert and disabled while closed. */}
                <div
                    data-testid="business-slot"
                    data-open={needsBusiness ? 'true' : 'false'}
                    className={cn(
                        'grid transition-[grid-template-rows,opacity] duration-200 ease-out motion-reduce:transition-none',
                        needsBusiness
                            ? 'grid-rows-[1fr] opacity-100'
                            : 'grid-rows-[0fr] opacity-0',
                    )}
                    inert={!needsBusiness}
                    aria-hidden={needsBusiness ? undefined : true}
                >
                    <div className="-mx-1 min-h-0 overflow-hidden px-1">
                        <div className="pt-4 pb-1">
                            {remembered ? (
                                <div className="flex items-center gap-2.5 rounded-lg border border-border bg-muted p-2 text-xs">
                                    <TenantLogo
                                        url={business.logo_url}
                                        name={business.name}
                                        className="size-7 shrink-0"
                                        imageClassName="p-0.5"
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block text-[10px] text-muted-foreground">
                                            {t('login.business')}
                                        </span>
                                        <strong className="block truncate font-semibold text-foreground">
                                            {business.name}
                                        </strong>
                                    </span>
                                    <input
                                        type="hidden"
                                        name="business"
                                        value={business.code}
                                        disabled={!needsBusiness}
                                    />
                                    <button
                                        type="button"
                                        className="min-h-11 rounded-md px-2 text-[11px] font-medium text-primary hover:underline focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none md:min-h-7"
                                        onClick={() => setChanging(true)}
                                        aria-label={`${t('login.change_business')}: ${business.name}`}
                                    >
                                        {t('login.change_business')}
                                    </button>
                                </div>
                            ) : (
                                <div>
                                    <label htmlFor="business" className={LABEL}>
                                        {t('login.business_code')}
                                    </label>
                                    <div className="relative mt-1">
                                        <input
                                            ref={businessRef}
                                            id="business"
                                            name="business"
                                            type="text"
                                            required={needsBusiness}
                                            disabled={!needsBusiness}
                                            autoComplete="organization"
                                            autoCapitalize="none"
                                            spellCheck={false}
                                            defaultValue={business?.code ?? ''}
                                            placeholder={t(
                                                'login.business_placeholder',
                                            )}
                                            aria-describedby="business-hint"
                                            aria-invalid={
                                                errors.business
                                                    ? true
                                                    : undefined
                                            }
                                            className={cn(FIELD, 'pr-3')}
                                        />
                                        <Building2
                                            className={FIELD_ICON}
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <p
                                        id="business-hint"
                                        className="mt-1 text-[11px] text-muted-foreground"
                                    >
                                        {t('login.business_hint')}
                                    </p>
                                    {errors.business ? (
                                        <p className={FIELD_ERROR}>
                                            {errors.business}
                                        </p>
                                    ) : null}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div className="flex items-center justify-between">
                    <label htmlFor="password" className={LABEL}>
                        {t('login.password')}
                    </label>
                    {canResetPassword ? (
                        <TextLink
                            href={request()}
                            className="inline-flex min-h-11 items-center text-[11px] text-primary no-underline hover:underline md:min-h-0"
                        >
                            {t('login.forgot')}
                        </TextLink>
                    ) : null}
                </div>
                <div className="relative mt-1">
                    <input
                        ref={passwordRef}
                        id="password"
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        required
                        autoComplete="current-password"
                        enterKeyHint="go"
                        placeholder={t('login.password_placeholder')}
                        onKeyDown={trackCaps}
                        onKeyUp={trackCaps}
                        onBlur={() => setCapsLock(false)}
                        aria-invalid={errors.password ? true : undefined}
                        aria-describedby={
                            [
                                capsLock ? 'caps-lock' : null,
                                errors.password ? 'password-error' : null,
                            ]
                                .filter(Boolean)
                                .join(' ') || undefined
                        }
                        className={cn(FIELD, 'pr-11 md:pr-9')}
                    />
                    <Lock className={FIELD_ICON} aria-hidden="true" />
                    <button
                        type="button"
                        onClick={() => setShowPassword((shown) => !shown)}
                        aria-label={toggleLabel}
                        title={toggleLabel}
                        aria-pressed={showPassword}
                        aria-controls="password"
                        className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:outline-none md:w-9"
                    >
                        {showPassword ? (
                            <EyeOff className="size-3.5" aria-hidden="true" />
                        ) : (
                            <Eye className="size-3.5" aria-hidden="true" />
                        )}
                    </button>
                </div>
                {errors.password ? (
                    <p id="password-error" className={FIELD_ERROR}>
                        {errors.password}
                    </p>
                ) : null}
            </div>

            <div className="flex items-center justify-between gap-2 text-xs">
                <label className="flex min-h-11 cursor-pointer items-center gap-2 md:min-h-0">
                    <input
                        type="checkbox"
                        id="remember"
                        name="remember"
                        className="size-3.5 rounded border-border accent-primary"
                    />
                    <span className="text-[11px] text-muted-foreground">
                        {t('login.remember')}
                    </span>
                </label>
                {/* The mockup's "failed tries" slot holds the Caps Lock warning, so it never shifts the form. */}
                <span
                    id="caps-lock"
                    data-testid="caps-lock"
                    aria-live="polite"
                    className="flex items-center gap-1 text-[10px] font-medium text-warning"
                >
                    {capsLock ? (
                        <>
                            <TriangleAlert
                                className="size-3"
                                aria-hidden="true"
                            />
                            {t('login.caps_lock')}
                        </>
                    ) : null}
                </span>
            </div>

            <button
                type="submit"
                disabled={processing || locked}
                data-test="login-button"
                className="flex min-h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-lg bg-primary text-sm font-bold text-primary-foreground shadow-xs transition-colors hover:bg-primary-hover focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-card focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 md:h-9 md:min-h-9 md:text-xs"
            >
                {processing ? <Spinner aria-hidden="true" /> : null}
                {processing
                    ? t('login.signing_in')
                    : locked
                      ? t('login.retry_in', { seconds: remaining })
                      : t('login.submit')}
            </button>
        </div>
    );
}

Login.layout = (page: ReactNode) => (
    <TranslatedAuthLayout
        titleKey="login.heading"
        descriptionKey="login.description"
        titleSrOnly
    >
        {page}
    </TranslatedAuthLayout>
);
