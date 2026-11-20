import { useApi, type ApiResponse } from '@/hooks/use-api';
import { GraduationCap } from 'lucide-react';
import { useEffect, useState } from 'react';

type ApiEnvelope<T> = { data: T };

export type EnrollmentDetail = {
    /** Database key used to request enrollment-specific overview data. */
    id: number;
    enrollment_code: string;
    enrollment_date: string | null;
    starts_on: string | null;
    expected_completion_on: string | null;
    status: string;
    course: { code: string; name: string } | null;
    branch: { name: string } | null;
    batch: { name: string } | null;
};

type ExamAttempt = {
    id: number;
    name: string;
    attempt_number: number;
    attempt_status: string;
    result_status: string;
    exam_date: string | null;
    total_marks: string | null;
    obtained_marks: string | null;
    grade: string | null;
    result_published_on: string | null;
};

type AcademicStage = {
    id: number;
    stage: { name: string; sequence_number: number };
    status: string;
    started_on: string | null;
    expected_completion_on: string | null;
    completed_on: string | null;
    exams: ExamAttempt[];
};

type AcademicOverview = {
    stages: AcademicStage[];
    completion: {
        eligible: boolean;
        outstanding_requirements: string[];
    };
    certificate_eligibility: {
        eligible: boolean;
        status: string;
        outstanding_requirements: string[];
    };
};

type CertificateReissue = {
    id: number;
    status: string;
    reason: string;
    requested_at: string | null;
    decision_at: string | null;
    replacement_certificate: {
        certificate_number: string | null;
        status: string;
    } | null;
};

type EnrollmentCertificate = {
    id: number;
    certificate_number: string | null;
    name: string | null;
    course_title: string;
    status: string;
    issued_on: string | null;
    printed_at: string | null;
    delivered_at: string | null;
    received_by: string | null;
    delivery_method: string | null;
    delivery_reference: string | null;
    reissue_history: CertificateReissue[];
};

type EnrollmentPayment = {
    payment_number: string;
    amount: string;
    paid_at: string | null;
    payment_method: string | null;
    status: string;
    transaction_reference: string | null;
    receipt_path?: string | null;
};

type TuitionBill = {
    tuition_number: string;
    status: string;
    original_amount: string;
    approved_waiver: string;
    payable_amount: string;
    due_on: string | null;
    payments: EnrollmentPayment[];
};

type OtherBill = {
    expense_number: string;
    description: string;
    status: string;
    issued_on: string | null;
    due_on: string | null;
    amount: string;
    approved_waiver: string;
    payable_amount: string;
    outstanding_due: string;
    payments: EnrollmentPayment[];
};

type EnrollmentFinancialSummary = {
    total_course_fee: string;
    additional_fees: string;
    approved_waiver: string;
    payable_fee: string;
    total_paid: string;
    outstanding_due: string;
    financial_status: string | null;
    bill: TuitionBill | null;
    other_bills: OtherBill[];
};

type SectionResult<T> = {
    data: T | null;
    error: string | null;
};

const initialSectionResult: SectionResult<never> = {
    data: null,
    error: null,
};

const positiveStatuses = new Set([
    'appeared',
    'completed',
    'confirmed',
    'delivered',
    'eligible',
    'enrolled',
    'issued',
    'ongoing',
    'paid',
    'passed',
]);
const attentionStatuses = new Set([
    'due',
    'eligibility_pending',
    'on_hold',
    'partial',
    'pending',
    'processing',
    'ready',
    'scheduled',
    'unpaid',
    'under_review',
]);
const negativeStatuses = new Set([
    'absent',
    'cancelled',
    'discontinued',
    'failed',
    'not_eligible',
    'overdue',
    'rejected',
]);

async function loadSection<T>(
    request: Promise<ApiResponse<ApiEnvelope<T>>>,
    unavailableMessage: string,
): Promise<SectionResult<T>> {
    try {
        const response = await request;

        return { data: response.data.data, error: null };
    } catch (error) {
        const message =
            error instanceof Error ? error.message : unavailableMessage;

        return { data: null, error: message || unavailableMessage };
    }
}

export function EnrollmentStatusDetails({
    enrollment,
}: {
    enrollment: EnrollmentDetail;
}) {
    const { request } = useApi();
    const [academic, setAcademic] =
        useState<SectionResult<AcademicOverview>>(initialSectionResult);
    const [certificates, setCertificates] =
        useState<SectionResult<EnrollmentCertificate[]>>(initialSectionResult);
    const [financial, setFinancial] =
        useState<SectionResult<EnrollmentFinancialSummary>>(
            initialSectionResult,
        );
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const controller = new AbortController();
        setAcademic(initialSectionResult);
        setCertificates(initialSectionResult);
        setFinancial(initialSectionResult);
        setLoading(true);

        async function loadOverview(): Promise<void> {
            // Load sections independently so one failed endpoint does not hide other enrollment data.
            const [academicResult, certificateResult, financialResult] =
                await Promise.all([
                    loadSection(
                        request<ApiEnvelope<AcademicOverview>>(
                            `/enrollments/${enrollment.id}/academic`,
                            { signal: controller.signal },
                        ),
                        'Academic information is unavailable.',
                    ),
                    loadSection(
                        request<ApiEnvelope<EnrollmentCertificate[]>>(
                            `/enrollments/${enrollment.id}/certificates`,
                            { signal: controller.signal },
                        ),
                        'Certificate information is unavailable.',
                    ),
                    loadSection(
                        request<ApiEnvelope<EnrollmentFinancialSummary>>(
                            `/enrollments/${enrollment.id}/financial-summary`,
                            { signal: controller.signal },
                        ),
                        'Financial information is unavailable.',
                    ),
                ]);

            if (controller.signal.aborted) {
                return;
            }

            setAcademic(academicResult);
            setCertificates(certificateResult);
            setFinancial(financialResult);
            setLoading(false);
        }

        void loadOverview();

        return () => controller.abort();
    }, [enrollment.id, request]);

    const completedStages =
        academic.data?.stages.filter((stage) => stage.status === 'completed')
            .length ?? 0;
    const totalStages = academic.data?.stages.length ?? 0;
    const singleStage =
        academic.data?.stages.length === 1
            ? (academic.data.stages[0] ?? null)
            : null;
    const certificateReissues =
        certificates.data?.flatMap((certificate) =>
            certificate.reissue_history.map((reissue) => ({
                certificate,
                reissue,
            })),
        ) ?? [];

    return (
        <article className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-mono text-xs text-slate-400">
                        {enrollment.enrollment_code}
                    </p>
                    <h4 className="mt-1 font-semibold text-[#1B365D]">
                        {enrollment.course?.name ?? 'Course not recorded'}
                    </h4>
                    <p className="mt-1 text-xs text-slate-500">
                        {[
                            enrollment.course?.code,
                            enrollment.branch?.name,
                            enrollment.batch?.name,
                        ]
                            .filter(Boolean)
                            .join(' · ') || 'Course details not recorded'}
                    </p>
                </div>
                <StatusBadge status={enrollment.status} />
            </header>

            <div className="grid gap-3 text-xs sm:grid-cols-3">
                <EnrollmentDetail
                    label="Enrolled"
                    value={enrollment.enrollment_date}
                />
                <EnrollmentDetail label="Starts" value={enrollment.starts_on} />
                <EnrollmentDetail
                    label="Expected completion"
                    value={enrollment.expected_completion_on}
                />
            </div>

            <section className="rounded-xl border border-slate-100 p-4">
                <SectionHeading
                    title="Academic"
                    note={
                        academic.data
                            ? `${completedStages} of ${totalStages} stages completed.`
                            : 'Enrollment stages and examination attempts.'
                    }
                />
                {loading && <LoadingMessage />}
                {!loading && academic.error && (
                    <SectionError message={academic.error} />
                )}
                {!loading && academic.data?.stages.length === 0 && (
                    <EmptyMessage message="No academic stages are recorded for this enrollment." />
                )}
                {academic.data && (
                    <div className="mt-3 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[#1B365D]/15 bg-gradient-to-r from-[#1B365D]/5 to-white p-4 shadow-sm">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-white text-[#1B365D] shadow-sm ring-1 ring-[#1B365D]/10">
                                    <GraduationCap
                                        aria-hidden="true"
                                        size={19}
                                    />
                                </span>
                                <div>
                                    <p className="text-sm font-semibold text-[#1B365D]">
                                        Enrollment
                                    </p>
                                    <p className="mt-0.5 text-xs text-slate-500">
                                        Current course status
                                    </p>
                                </div>
                            </div>
                            <StatusBadge status={enrollment.status} />
                        </div>
                        {/* A single-stage course is presented as an examination; multiple stages are semesters. */}
                        {singleStage ? (
                            <div
                                key={singleStage.id}
                                className="rounded-lg border border-slate-100 bg-slate-50 p-3"
                            >
                                <h6 className="text-xs font-semibold text-slate-700">
                                    Examination
                                </h6>
                                <AcademicExams
                                    exams={singleStage.exams}
                                    emptyMessage="Examination status is not recorded."
                                />
                            </div>
                        ) : (
                            academic.data.stages.length > 0 && (
                                <div className="space-y-3 border-t border-slate-100 pt-3">
                                    <h6 className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                                        Semester progress
                                    </h6>
                                    {academic.data.stages.map((stage) => (
                                        <div
                                            key={stage.id}
                                            className="rounded-lg border border-slate-100 bg-slate-50 p-3"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <div>
                                                    <h6 className="text-xs font-semibold text-slate-700">
                                                        Semester{' '}
                                                        {
                                                            stage.stage
                                                                .sequence_number
                                                        }
                                                    </h6>
                                                    <p className="mt-1 text-[11px] text-slate-400">
                                                        {stage.stage.name}
                                                    </p>
                                                </div>
                                                <StatusBadge
                                                    status={stage.status}
                                                />
                                            </div>
                                            <AcademicExams
                                                exams={stage.exams}
                                            />
                                        </div>
                                    ))}
                                </div>
                            )
                        )}
                    </div>
                )}
            </section>

            <section className="rounded-xl border border-slate-100 p-4">
                <SectionHeading
                    title="Certificates"
                    note="Certificate status and delivery information for this enrollment."
                />
                {loading && <LoadingMessage />}
                {!loading && certificates.error && (
                    <SectionError message={certificates.error} />
                )}
                {!loading && certificates.data?.length === 0 && (
                    <EmptyMessage message="No certificate records exist for this enrollment." />
                )}
                {certificates.data && certificates.data.length > 0 && (
                    <div className="mt-3 divide-y divide-slate-100">
                        {certificates.data.map((certificate, index) => (
                            <div
                                key={certificate.id}
                                className="py-3 first:pt-0 last:pb-0"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className="text-xs font-medium text-slate-700">
                                            Certificate {index + 1}:{' '}
                                            {certificate.name ??
                                                certificate.course_title}
                                        </p>
                                        {certificate.certificate_number && (
                                            <p className="mt-1 font-mono text-[11px] text-slate-400">
                                                {certificate.certificate_number}
                                            </p>
                                        )}
                                    </div>
                                    <StatusBadge status={certificate.status} />
                                </div>
                                <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-slate-500">
                                    {certificate.issued_on && (
                                        <span>
                                            Issued{' '}
                                            {formatDate(certificate.issued_on)}
                                        </span>
                                    )}
                                    {certificate.delivered_at && (
                                        <span>
                                            Delivered{' '}
                                            {formatDate(
                                                certificate.delivered_at,
                                            )}
                                        </span>
                                    )}
                                    {certificate.delivery_method && (
                                        <span>
                                            Method:{' '}
                                            {certificate.delivery_method}
                                        </span>
                                    )}
                                    {certificate.received_by && (
                                        <span>
                                            Received by:{' '}
                                            {certificate.received_by}
                                        </span>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
                {!loading && !certificates.error && certificates.data && (
                    <div className="mt-4 border-t border-slate-100 pt-4">
                        <SectionHeading
                            title="Certificate reissues"
                            note="Reissue requests and their statuses for this enrollment."
                        />
                        {certificateReissues.length === 0 ? (
                            <EmptyMessage message="No certificate reissue requests exist for this enrollment." />
                        ) : (
                            <div className="mt-3 divide-y divide-slate-100">
                                {certificateReissues.map(
                                    ({ certificate, reissue }) => (
                                        <div
                                            key={reissue.id}
                                            className="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                                        >
                                            <div>
                                                <p className="text-xs font-medium text-slate-700">
                                                    {certificate.name ??
                                                        certificate.course_title}
                                                </p>
                                                <p className="mt-1 text-[11px] text-slate-500">
                                                    {reissue
                                                        .replacement_certificate
                                                        ?.certificate_number ??
                                                        reissue.reason}
                                                </p>
                                            </div>
                                            <StatusBadge
                                                status={reissue.status}
                                            />
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </div>
                )}
            </section>

            <section className="rounded-xl border border-slate-100 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <SectionHeading
                        title="Financial status"
                        note="Tuition and enrollment expenses with confirmed payments and balances."
                    />
                    {financial.data?.financial_status && (
                        <StatusBadge status={financial.data.financial_status} />
                    )}
                </div>
                {loading && <LoadingMessage />}
                {!loading && financial.error && (
                    <SectionError message={financial.error} />
                )}
                {financial.data && (
                    <>
                        <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <MoneyDetail
                                label="Total course fee"
                                amount={financial.data.total_course_fee}
                            />
                            <MoneyDetail
                                label="Additional fees"
                                amount={financial.data.additional_fees}
                            />
                            <MoneyDetail
                                label="Approved waiver"
                                amount={financial.data.approved_waiver}
                            />
                            <MoneyDetail
                                label="Payable fee"
                                amount={financial.data.payable_fee}
                            />
                            <MoneyDetail
                                label="Total paid"
                                amount={financial.data.total_paid}
                            />
                            <MoneyDetail
                                label="Outstanding due"
                                amount={financial.data.outstanding_due}
                            />
                        </div>
                        <div className="mt-4 border-t border-slate-200 pt-4">
                            <SectionHeading
                                title="Enrollment payment records"
                                note="Payment records are grouped under the bill they were applied to."
                            />
                            {financial.data.bill && (
                                <div className="mt-3">
                                    <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-mono text-xs text-slate-600">
                                            Tuition{' '}
                                            {financial.data.bill.tuition_number}
                                        </p>
                                        <StatusBadge
                                            status={financial.data.bill.status}
                                        />
                                    </div>
                                    <PaymentList
                                        payments={financial.data.bill.payments}
                                    />
                                </div>
                            )}
                            {financial.data.other_bills.map((bill) => (
                                <div
                                    key={bill.expense_number}
                                    className="mt-4 border-t border-slate-100 pt-3"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-xs font-medium text-slate-700">
                                                {bill.description}
                                            </p>
                                            <p className="mt-1 font-mono text-[11px] text-slate-400">
                                                {bill.expense_number}
                                            </p>
                                        </div>
                                        <StatusBadge status={bill.status} />
                                    </div>
                                    <div className="mt-2 grid gap-2 text-[11px] text-slate-500 sm:grid-cols-3">
                                        <span>
                                            Amount: {formatMoney(bill.amount)}
                                        </span>
                                        <span>
                                            Waiver:{' '}
                                            {formatMoney(bill.approved_waiver)}
                                        </span>
                                        <span>
                                            Due:{' '}
                                            {formatMoney(bill.outstanding_due)}
                                        </span>
                                    </div>
                                    <PaymentList payments={bill.payments} />
                                </div>
                            ))}
                            {!financial.data.bill &&
                                financial.data.other_bills.length === 0 && (
                                    <EmptyMessage message="No bills or payments are recorded for this enrollment." />
                                )}
                        </div>
                    </>
                )}
            </section>
        </article>
    );
}

function AcademicExams({
    exams,
    emptyMessage = 'No examinations recorded.',
}: {
    exams: ExamAttempt[];
    emptyMessage?: string;
}) {
    if (exams.length === 0) {
        return <p className="mt-2 text-xs text-slate-400">{emptyMessage}</p>;
    }

    return (
        <div className="mt-2 divide-y divide-slate-200">
            {exams.map((exam) => (
                <div
                    key={exam.id}
                    className="flex flex-wrap items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                >
                    <div>
                        <p className="text-xs text-slate-600">
                            {exam.name} · Attempt {exam.attempt_number}
                        </p>
                        {(exam.obtained_marks !== null ||
                            exam.grade !== null) && (
                            <p className="mt-1 text-[11px] text-slate-400">
                                {exam.obtained_marks !== null &&
                                exam.total_marks !== null
                                    ? `${exam.obtained_marks} / ${exam.total_marks} marks`
                                    : null}
                                {exam.grade ? ` · Grade ${exam.grade}` : ''}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <StatusBadge
                            label="Attempt"
                            status={exam.attempt_status}
                        />
                        <StatusBadge
                            label="Result"
                            status={exam.result_status}
                        />
                    </div>
                </div>
            ))}
        </div>
    );
}

function PaymentList({ payments }: { payments: EnrollmentPayment[] }) {
    if (payments.length === 0) {
        return (
            <p className="mt-2 text-xs text-slate-400">
                No payments recorded for this bill.
            </p>
        );
    }

    return (
        <div className="divide-y divide-slate-100">
            {payments.map((payment) => (
                <div
                    key={payment.payment_number}
                    className="flex flex-wrap items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                >
                    <div>
                        <p className="font-mono text-xs font-medium text-slate-700">
                            {payment.payment_number}
                        </p>
                        <p className="mt-0.5 text-[11px] text-slate-400">
                            {payment.paid_at
                                ? formatDate(payment.paid_at)
                                : 'Date not recorded'}
                        </p>
                        <p className="mt-0.5 text-[11px] text-slate-400">
                            {[
                                payment.payment_method,
                                payment.transaction_reference
                                    ? `Ref ${payment.transaction_reference}`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ') ||
                                'Method or reference not recorded'}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <span className="text-xs font-semibold text-slate-700">
                            {formatMoney(payment.amount)}
                        </span>
                        <StatusBadge status={payment.status} />
                    </div>
                </div>
            ))}
        </div>
    );
}

function SectionHeading({ title, note }: { title: string; note: string }) {
    return (
        <div>
            <h5 className="text-sm font-semibold text-[#1B365D]">{title}</h5>
            <p className="mt-1 text-[11px] text-slate-400">{note}</p>
        </div>
    );
}

function EnrollmentDetail({
    label,
    value,
}: {
    label: string;
    value: string | null;
}) {
    return (
        <div>
            <p className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                {label}
            </p>
            <p className="mt-1 text-slate-700">{value || '—'}</p>
        </div>
    );
}

function MoneyDetail({
    label,
    amount,
}: {
    label: string;
    amount: string | null;
}) {
    return (
        <div>
            <p className="text-[10px] font-semibold tracking-wide text-slate-400 uppercase">
                {label}
            </p>
            <p className="mt-1 text-sm font-medium text-slate-700">
                {amount === null ? '—' : formatMoney(amount)}
            </p>
        </div>
    );
}

function LoadingMessage() {
    return (
        <p role="status" className="mt-3 text-xs text-slate-400">
            Loading enrollment details…
        </p>
    );
}

function EmptyMessage({ message }: { message: string }) {
    return <p className="mt-3 text-xs text-slate-400">{message}</p>;
}

function SectionError({ message }: { message: string }) {
    return (
        <p
            role="alert"
            className="mt-3 rounded-lg border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700"
        >
            {message}
        </p>
    );
}

function StatusBadge({ status, label }: { status: string; label?: string }) {
    const normalizedStatus = status.toLowerCase();
    const tone = positiveStatuses.has(normalizedStatus)
        ? 'bg-emerald-50 text-emerald-700'
        : attentionStatuses.has(normalizedStatus)
          ? 'bg-amber-50 text-amber-700'
          : negativeStatuses.has(normalizedStatus)
            ? 'bg-rose-50 text-rose-700'
            : 'bg-slate-100 text-slate-600';
    const value = status.replaceAll('_', ' ');

    return (
        <span
            className={`inline-flex items-center gap-1 rounded-full px-2 py-1 text-[10px] font-semibold capitalize ${tone}`}
        >
            {label && <span className="font-medium opacity-70">{label}:</span>}
            {value}
        </span>
    );
}

function formatMoney(amount: string): string {
    return new Intl.NumberFormat('en-BD', {
        style: 'currency',
        currency: 'BDT',
    }).format(Number(amount));
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('en-BD', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
