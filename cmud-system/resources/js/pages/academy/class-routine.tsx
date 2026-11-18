import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { CalendarDays, Clock3, Construction } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academy', href: '/academy/students' },
    { title: 'Class routine', href: '/academy/class-routine' },
];

/** Placeholder page for the upcoming class scheduling feature. */
export default function ClassRoutine() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Class routine | CMUD" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-5xl space-y-6">
                    <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-7 text-white shadow-lg sm:p-9">
                        <div className="flex items-center gap-3 text-cyan-100">
                            <CalendarDays size={20} />
                            <span className="text-xs font-semibold tracking-[0.16em] uppercase">Academy</span>
                        </div>
                        <h1 className="mt-4 text-3xl font-semibold tracking-tight">Class routine</h1>
                        <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">A future workspace for planning and viewing class schedules by course, module, teacher, and room.</p>
                    </header>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8" aria-labelledby="class-routine-placeholder-title">
                        <div className="flex items-start gap-4">
                            <span className="grid size-12 shrink-0 place-items-center rounded-xl bg-cyan-50 text-[#087586]"><Construction size={22} /></span>
                            <div>
                                <h2 id="class-routine-placeholder-title" className="text-lg font-semibold text-[#17375C]">Scheduling tools are coming soon</h2>
                                <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">This page is a placeholder. Class times, assigned teachers, rooms, and recurring schedules will appear here when the routine feature is implemented.</p>
                                <div className="mt-5 inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-600"><Clock3 size={14} /> Not available yet</div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
