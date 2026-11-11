import { Head, Link } from '@inertiajs/react';

export default function NotFound() {
    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-950 px-6 py-16 text-white">
            <Head title="Page not found" />

            <section className="w-full max-w-xl text-center">
                <p className="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-300">
                    CMUD System
                </p>
                <p className="mt-8 text-8xl font-semibold tracking-tight text-white">404</p>
                <h1 className="mt-4 text-3xl font-semibold tracking-tight">Page not found</h1>
                <p className="mt-4 text-base leading-7 text-slate-300">
                    The page you requested does not exist or may have moved.
                </p>
                <Link
                    href="/"
                    className="mt-8 inline-flex rounded-xl bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200"
                >
                    Back to home
                </Link>
            </section>
        </main>
    );
}
