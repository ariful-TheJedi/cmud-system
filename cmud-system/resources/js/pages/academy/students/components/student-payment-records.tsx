import type { ReactNode } from 'react';

export type StudentPaymentRecord = {
    key: string;
    payment_number: string;
    amount: string;
    paid_at: string | null;
    status: string;
    transaction_reference: string | null;
    payment_method: string | null;
    source: string;
};

export function StudentPaymentRecords({
    payments,
}: {
    payments: StudentPaymentRecord[];
}) {
    return (
        <section className="space-y-3">
            <header>
                <h3 className="text-base font-semibold text-[#1B365D]">
                    Student payment records
                </h3>
                <p className="mt-1 text-xs text-slate-500">
                    All tuition and other-expense payments recorded for this
                    student.
                </p>
            </header>

            {payments.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-8 text-center text-sm text-slate-400">
                    No payment records found.
                </div>
            ) : (
                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full divide-y divide-slate-100 text-left text-xs">
                        <thead className="bg-slate-50 text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                            <tr>
                                <TableHeading>Date</TableHeading>
                                <TableHeading>Payment</TableHeading>
                                <TableHeading>Source</TableHeading>
                                <TableHeading>Method / reference</TableHeading>
                                <TableHeading>Amount</TableHeading>
                                <TableHeading>Status</TableHeading>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {payments.map((payment) => (
                                <tr key={payment.key}>
                                    <TableCell>
                                        {formatDate(payment.paid_at)}
                                    </TableCell>
                                    <TableCell className="font-medium text-slate-700">
                                        {payment.payment_number}
                                    </TableCell>
                                    <TableCell>{payment.source}</TableCell>
                                    <TableCell>
                                        {[
                                            payment.payment_method,
                                            payment.transaction_reference,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </TableCell>
                                    <TableCell className="font-semibold text-slate-700">
                                        {formatMoney(payment.amount)}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={payment.status} />
                                    </TableCell>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}

function TableHeading({ children }: { children: ReactNode }) {
    return <th className="px-4 py-3 whitespace-nowrap">{children}</th>;
}

function TableCell({
    children,
    className = '',
}: {
    children: ReactNode;
    className?: string;
}) {
    return (
        <td
            className={`px-4 py-3 whitespace-nowrap text-slate-500 ${className}`}
        >
            {children}
        </td>
    );
}

function StatusBadge({ status }: { status: string }) {
    const normalizedStatus = status.toLowerCase();
    const tone =
        normalizedStatus === 'confirmed'
            ? 'bg-emerald-50 text-emerald-700'
            : ['cancelled', 'failed', 'rejected'].includes(normalizedStatus)
              ? 'bg-rose-50 text-rose-700'
              : 'bg-amber-50 text-amber-700';
    const label = status.replaceAll('_', ' ');

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold capitalize ${tone}`}
        >
            {label}
        </span>
    );
}

function formatMoney(amount: string): string {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
    }).format(Number(amount));
}

function formatDate(value: string | null): string {
    return value
        ? new Date(value).toLocaleDateString('en-BD', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          })
        : 'Date not recorded';
}
