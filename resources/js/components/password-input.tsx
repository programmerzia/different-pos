import { Eye, EyeOff } from 'lucide-react';
import type { ComponentProps, Ref } from 'react';
import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { useT } from '@/i18n';
import { cn } from '@/lib/utils';

/**
 * A password field with a show/hide toggle. The toggle stays out of the Tab order unless `revealTabbable`
 * (the sign-in form puts it in, so it can be reached without a mouse).
 */
export default function PasswordInput({
    className,
    ref,
    revealTabbable = false,
    ...props
}: Omit<ComponentProps<'input'>, 'type'> & {
    ref?: Ref<HTMLInputElement>;
    revealTabbable?: boolean;
}) {
    const { t } = useT();
    const [showPassword, setShowPassword] = useState(false);

    return (
        <div className="relative">
            <Input
                type={showPassword ? 'text' : 'password'}
                className={cn('pr-12', className)}
                ref={ref}
                {...props}
            />
            <button
                type="button"
                onClick={() => setShowPassword((prev) => !prev)}
                className="absolute inset-y-0 right-0 flex min-w-11 items-center justify-center rounded-r-md px-3 text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring focus-visible:outline-none"
                aria-label={
                    showPassword
                        ? t('password_input.hide')
                        : t('password_input.show')
                }
                aria-pressed={showPassword}
                aria-controls={props.id}
                tabIndex={revealTabbable ? 0 : -1}
            >
                {showPassword ? (
                    <EyeOff className="size-4" aria-hidden="true" />
                ) : (
                    <Eye className="size-4" aria-hidden="true" />
                )}
            </button>
        </div>
    );
}
