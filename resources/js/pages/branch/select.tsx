import { Head } from '@inertiajs/react';

type Branch = { id: number; name: string; code: string };

export default function BranchSelect({ branches }: { branches: Branch[] }) {
    return (
        <>
            <Head title="Choose a branch" />
            <div className="mx-auto flex w-full max-w-md flex-col gap-4 p-4">
                <h1 className="text-xl font-semibold">Choose a branch</h1>
                <ul className="flex flex-col gap-2">
                    {branches.map((branch) => (
                        <li key={branch.id}>
                            <button
                                type="button"
                                className="flex min-h-11 w-full items-center gap-3 rounded-xl border border-sidebar-border bg-card px-4 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                <span className="font-mono text-sm text-muted-foreground">
                                    {branch.code}
                                </span>
                                <span>{branch.name}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

BranchSelect.layout = {
    breadcrumbs: [{ title: 'Choose a branch', href: '/app/branch/select' }],
};
