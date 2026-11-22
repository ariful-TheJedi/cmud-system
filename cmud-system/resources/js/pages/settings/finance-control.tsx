import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Wallet } from 'lucide-react';

export default function FinanceControl() {
    return (
        <AppLayout breadcrumbs={[
            { title: 'Settings', href: '/settings/institute' },
            { title: 'Finance Control', href: '/settings/finance-control' },
        ]}>
            <Head title="Finance Control" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-5xl space-y-6">
                    <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-7 text-white sm:p-9">
                        <p className="flex items-center gap-3 text-xs font-semibold tracking-widest text-cyan-100 uppercase"><Wallet size={20} /> Settings</p>
                        <h1 className="mt-4 text-3xl font-semibold">Finance Control</h1>
                        <p className="mt-2 text-sm text-cyan-50">A workspace for managing finance settings.</p>
                    </header>
                    <section className="rounded-2xl border border-slate-200 bg-white p-6">
                        <h2 className="text-lg font-semibold text-[#17375C]">Finance settings</h2>
                        <p className="mt-2 text-sm text-slate-500">Finance controls will be available here once configured. This demo page does not change financial records.</p>
                    </section>
                    <section aria-label="Finance reports" className="grid gap-4 sm:grid-cols-2">
                        {[
                            { title: 'Student revenue', href: '/finance/student-revenue', description: 'View income from student payments.' },
                            { title: 'Institute expenditure', href: '/finance/institute-expenditure', description: 'View institute expenses and payment totals.' },
                        ].map((item) => (
                            <Link key={item.href} href={item.href} className="rounded-2xl border border-slate-200 bg-white p-6 hover:border-teal-600 focus-visible:outline-2 focus-visible:outline-teal-600">
                                <h2 className="font-semibold text-[#17375C]">{item.title}</h2>
                                <p className="mt-2 text-sm text-slate-500">{item.description}</p>
                            </Link>
                        ))}
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
