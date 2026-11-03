import type { SVGAttributes } from 'react';

/** Neutral storefront mark (no product brand): awning over a shop front with a door. Fills with currentColor. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            fill="currentColor"
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
        >
            <path d="M4.5 2.5h15L22 8.5a2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1-5 0 2.5 2.5 0 0 1-5 0Z" />
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M4 12.25h16V21.5H4Zm6 3.25h4v6h-4Zm-4 0h2.5v2.5H6Zm9.5 0H18v2.5h-2.5Z"
            />
        </svg>
    );
}
