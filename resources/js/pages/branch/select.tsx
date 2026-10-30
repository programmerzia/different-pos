import { Head } from '@inertiajs/react';

type Branch = { id: number; name: string; code: string };

// Placeholder: the real branch selector (keyboard-first, i18n) is plan step 1.12.
export default function BranchSelect({ branches }: { branches: Branch[] }) {
    return (
        <>
            <Head title="Branch" />
            <ul>
                {branches.map((branch) => (
                    <li key={branch.id}>
                        {branch.code} {branch.name}
                    </li>
                ))}
            </ul>
        </>
    );
}
