import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 42"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M20 2C11.7 2 5 8.7 5 17c0 10.5 15 23 15 23s15-12.5 15-23C35 8.7 28.3 2 20 2Zm0 9.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11Z"
            />
        </svg>
    );
}
