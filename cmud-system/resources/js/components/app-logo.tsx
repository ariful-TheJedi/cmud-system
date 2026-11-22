import { usePage } from '@inertiajs/react';

export default function AppLogo() {
    const { branding } = usePage().props;
    return (
        <>
            <div className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white">
                <img
                    src={branding?.logo_url ?? '/apple-touch-icon.png'}
                    alt=""
                    aria-hidden="true"
                    className="size-full object-contain"
                />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-tight font-semibold">
                    CMUD ERP
                </span>
            </div>
        </>
    );
}
