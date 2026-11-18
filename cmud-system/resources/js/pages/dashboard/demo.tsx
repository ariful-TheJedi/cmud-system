import { useEffect, useState } from 'react';
import { BookOpen, CalendarDays, GraduationCap, Users } from 'lucide-react';
import { ApiRequestError, useApi } from '@/hooks/use-api';

type DashboardMetrics = {
    students: number;
    courses: number;
    teachers: number;
};

const metricCards = [
    { key: 'students', label: 'Students', icon: GraduationCap },
    { key: 'courses', label: 'Courses', icon: BookOpen },
    { key: 'teachers', label: 'Teachers', icon: Users },
    { key: 'upcomingClasses', label: 'Upcoming classes', icon: CalendarDays },
] as const;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function readTotal(value: unknown, resource: string): number {
    if (
        !isRecord(value) ||
        typeof value.total !== 'number' ||
        !Number.isInteger(value.total) ||
        value.total < 0
    ) {
        throw new Error(`The ${resource} API returned an invalid total.`);
    }

    return value.total;
}

export default function DemoDashboard() {
    const { request } = useApi();
    const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [retryCount, setRetryCount] = useState(0);

    useEffect(() => {
        const controller = new AbortController();

        async function loadMetrics() {
            setLoading(true);
            setError(null);

            try {
                const [studentsResponse, coursesResponse, teachersResponse] =
                    await Promise.all([
                        request<unknown>('/students', {
                            params: { per_page: 1 },
                            signal: controller.signal,
                        }),
                        request<unknown>('/academy-catalog/courses', {
                            params: { per_page: 1 },
                            signal: controller.signal,
                        }),
                        request<unknown>('/academy-catalog/teachers', {
                            signal: controller.signal,
                        }),
                    ]);
                const studentData = studentsResponse.data;
                const studentMeta = isRecord(studentData)
                    ? studentData.meta
                    : null;

                if (!isRecord(studentMeta)) {
                    throw new Error(
                        'The students API returned invalid pagination data.',
                    );
                }

                setMetrics({
                    students: readTotal(studentMeta, 'students'),
                    courses: readTotal(coursesResponse.data, 'courses'),
                    teachers: readTotal(teachersResponse.data, 'teachers'),
                });
            } catch (requestError) {
                if (!controller.signal.aborted) {
                    setError(
                        requestError instanceof ApiRequestError
                            ? requestError.status === null
                                ? requestError.message
                                : `HTTP ${requestError.status}: ${requestError.message}`
                            : requestError instanceof Error
                              ? requestError.message
                              : 'Unable to load dashboard data.',
                    );
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void loadMetrics();

        return () => controller.abort();
    }, [request, retryCount]);

    return (
        <div className="mx-auto max-w-7xl space-y-6">
            <header className="rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-7 text-white shadow-lg sm:p-9">
                <p className="text-xs font-semibold tracking-[0.16em] text-cyan-100 uppercase">
                    Academy overview
                </p>
                <h1 className="mt-4 text-3xl font-semibold tracking-tight">
                    Dashboard
                </h1>
                <p className="mt-2 max-w-2xl text-sm text-cyan-50/85">
                    A snapshot of current student, course, and teacher records.
                </p>
            </header>

            {error && (
                <div
                    role="alert"
                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                >
                    <p>Dashboard data could not be loaded: {error}</p>
                    <button
                        type="button"
                        onClick={() => setRetryCount((count) => count + 1)}
                        className="rounded-lg border border-rose-300 px-3 py-1.5 font-semibold hover:bg-rose-100"
                    >
                        Retry
                    </button>
                </div>
            )}

            <section
                className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                aria-label="Dashboard metrics"
                aria-busy={loading}
            >
                {metricCards.map(({ key, label, icon: Icon }) => {
                    const scheduleMetric = key === 'upcomingClasses';
                    const value = scheduleMetric
                        ? 'Unavailable'
                        : metrics
                          ? new Intl.NumberFormat().format(metrics[key])
                          : loading
                            ? 'Loading'
                            : 'Unavailable';
                    const description = scheduleMetric
                        ? 'Class schedule data is not available yet'
                        : error
                          ? 'Unable to refresh API data'
                          : loading && !metrics
                            ? 'Loading live data'
                            : 'Live data connected';

                    return (
                        <article
                            key={key}
                            className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                        >
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium text-slate-500">
                                    {label}
                                </p>
                                <span className="grid size-10 place-items-center rounded-xl bg-cyan-50 text-[#087586]">
                                    <Icon size={18} />
                                </span>
                            </div>
                            <p
                                className={`mt-5 font-semibold text-[#17375C] ${scheduleMetric ? 'text-sm' : 'text-3xl'}`}
                            >
                                {value}
                            </p>
                            <p className="mt-1 text-xs text-slate-400">
                                {description}
                            </p>
                        </article>
                    );
                })}
            </section>

            <section className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center shadow-sm">
                <h2 className="text-lg font-semibold text-[#17375C]">
                    Dashboard data
                </h2>
                <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-500">
                    Student, course, and teacher totals come from the Academy
                    APIs. Class schedule metrics will be available when class
                    routines are implemented.
                </p>
            </section>
        </div>
    );
}
