/**
 * Section heading for settings screens. `default` uses the mockup page-title scale (18 px bold, 12 px
 * muted description), `small` the card-section scale. For a screen's own title use `<PageHeader>`.
 */
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-6'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-sm font-semibold'
                        : 'text-lg leading-7 font-bold'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="mt-0.5 text-xs text-muted-foreground">
                    {description}
                </p>
            )}
        </header>
    );
}
