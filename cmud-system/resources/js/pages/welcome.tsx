import { Head } from '@inertiajs/react';

export default function Welcome() {
    return (
        <main className="flex min-h-screen items-center justify-center bg-slate-950 px-6 py-16 text-white">
            <Head title="CMUD System" />

            <section className="w-full max-w-3xl rounded-3xl border border-white/10 bg-white/[0.04] p-8 shadow-2xl shadow-black/30 sm:p-12">
                <div className="mb-10 flex items-center gap-3">
                    <div className="flex size-12 items-center justify-center rounded-2xl bg-cyan-400 font-bold tracking-tight text-slate-950">
                        C
                    </div>
                    <span className="text-sm font-semibold uppercase tracking-[0.24em] text-cyan-200">
                        CMUD System
                    </span>
                </div>

                <p className="mb-4 text-sm font-medium uppercase tracking-[0.2em] text-slate-400">
                    College Management
                </p>
                <h1 className="max-w-2xl text-4xl font-semibold tracking-tight sm:text-6xl">
                    Welcome to CMUD System
                </h1>
                <p className="mt-6 max-w-xl text-lg leading-8 text-slate-300">
                    A unified platform for managing institute operations, students, academy programs, and finance.
                </p>

                <div className="mt-10 flex flex-wrap items-center gap-4">
                    <a
                        href="/api/v1"
                        className="rounded-xl bg-cyan-300 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-200"
                    >
                        View API status
                    </a>
                    <span className="text-sm text-slate-400">API version 1</span>
                </div>
            </section>
        </main>
    );
}
