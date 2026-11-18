import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import DemoDashboard from './demo';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
];

export default function Dashboard() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard | CMUD" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10">
                <DemoDashboard />
            </main>
        </AppLayout>
    );
}
