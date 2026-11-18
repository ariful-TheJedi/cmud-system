import { Head } from '@inertiajs/react';
import { ShieldCheck, Users, UsersRound } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/institute' },
    { title: 'User Control', href: '/settings/user-control' },
];

const sampleUsers = [
    {
        name: 'Amina Rahman',
        email: 'amina@example.test',
        role: 'Administrator',
        lastActive: 'Today',
        active: true,
    },
    {
        name: 'Rafiq Hasan',
        email: 'rafiq@example.test',
        role: 'Academy manager',
        lastActive: 'Yesterday',
        active: true,
    },
    {
        name: 'Nabila Karim',
        email: 'nabila@example.test',
        role: 'Finance officer',
        lastActive: 'Nov 16, 2026',
        active: true,
    },
    {
        name: 'Sajid Ahmed',
        email: 'sajid@example.test',
        role: 'Teacher',
        lastActive: 'Nov 12, 2026',
        active: false,
    },
];

/** Provides sample user and permission data until management is connected. */
export default function UserControl() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Control | CMUD" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-5xl space-y-6">
                    <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-7 text-white shadow-lg sm:p-9">
                        <div className="flex items-center gap-3 text-cyan-100">
                            <Users size={20} />
                            <span className="text-xs font-semibold tracking-[0.16em] uppercase">
                                Settings
                            </span>
                        </div>
                        <h1 className="mt-4 text-3xl font-semibold tracking-tight">
                            It is Dummy User Control
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">
                            A future workspace for managing system users, roles,
                            and account access.
                        </p>
                    </header>

                    <div
                        role="note"
                        className="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                    >
                        <ShieldCheck size={17} />
                        <span>
                            <strong>Demo page:</strong> all users and counts
                            below are sample data; no changes can be made here.
                        </span>
                    </div>

                    <section
                        className="grid gap-4 sm:grid-cols-3"
                        aria-label="Sample user totals"
                    >
                        {[
                            ['Users', '8'],
                            ['Active accounts', '7'],
                            ['Permission groups', '4'],
                        ].map(([label, value]) => (
                            <article
                                key={label}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <p className="text-sm font-medium text-slate-500">
                                    {label}
                                </p>
                                <p className="mt-3 text-3xl font-semibold text-[#17375C]">
                                    {value}
                                </p>
                                <p className="mt-1 text-xs text-slate-400">
                                    Sample value
                                </p>
                            </article>
                        ))}
                    </section>

                    <section
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                        aria-labelledby="sample-users-title"
                    >
                        <header className="flex items-center gap-3 border-b border-slate-100 px-5 py-4">
                            <span className="grid size-10 place-items-center rounded-xl bg-cyan-50 text-[#087586]">
                                <UsersRound size={18} />
                            </span>
                            <div>
                                <h2
                                    id="sample-users-title"
                                    className="font-semibold text-[#17375C]"
                                >
                                    Users
                                </h2>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Example account and permission assignments
                                </p>
                            </div>
                        </header>
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[680px] text-left">
                                <thead className="bg-slate-50 text-[10px] font-bold tracking-[0.12em] text-slate-400 uppercase">
                                    <tr>
                                        <th className="px-5 py-3">User</th>
                                        <th className="px-5 py-3">
                                            Permission group
                                        </th>
                                        <th className="px-5 py-3">
                                            Last active
                                        </th>
                                        <th className="px-5 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {sampleUsers.map((user) => (
                                        <tr key={user.email}>
                                            <td className="px-5 py-4">
                                                <p className="text-sm font-semibold text-slate-700">
                                                    {user.name}
                                                </p>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {user.email}
                                                </p>
                                            </td>
                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {user.role}
                                            </td>
                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {user.lastActive}
                                            </td>
                                            <td className="px-5 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold ${user.active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
                                                >
                                                    {user.active
                                                        ? 'Active'
                                                        : 'Inactive'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <footer className="flex items-center gap-2 border-t border-slate-100 bg-slate-50/70 px-5 py-3 text-xs text-slate-500">
                            <Users size={14} /> User management and permission
                            editing are not connected yet.
                        </footer>
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
