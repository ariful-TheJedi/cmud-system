import { Head } from '@inertiajs/react';
import {
    Building2,
    ExternalLink,
    GitBranch,
    Globe2,
    LoaderCircle,
    Mail,
    MapPin,
    Pencil,
    Phone,
    Save,
    Upload,
    X,
} from 'lucide-react';
import { useEffect, useState, type ChangeEvent, type FormEvent } from 'react';
import AppLayout from '@/layouts/app-layout';
import { ApiRequestError, useApi } from '@/hooks/use-api';
import type { BreadcrumbItem } from '@/types';
import BranchManager from '@/components/branch-manager';

type InstituteOption = { id: number; code: string; name: string };
type Branch = {
    id: number;
    code: string;
    name: string;
    phone: string;
    email: string | null;
    address: string;
    is_active: boolean;
};
type Institute = InstituteOption & {
    phone: string;
    email: string;
    tin_number: string | null;
    bin_number: string | null;
    website: string | null;
    address: string;
    logo_path: string | null;
    logo_url: string | null;
    is_active: boolean;
    branches: Branch[];
};
type InstituteForm = {
    code: string;
    name: string;
    phone: string;
    email: string;
    tin_number: string;
    bin_number: string;
    website: string;
    address: string;
    is_active: boolean;
    logo: File | null;
    remove_logo: boolean;
};
type InstituteTextField =
    | 'code'
    | 'name'
    | 'phone'
    | 'email'
    | 'tin_number'
    | 'bin_number'
    | 'website';
type ApiEnvelope<T> = { data: T; message?: string };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings/institute' },
    { title: 'Institute', href: '/settings/institute' },
];

function emptyForm(): InstituteForm {
    return {
        code: '',
        name: '',
        phone: '',
        email: '',
        tin_number: '',
        bin_number: '',
        website: '',
        address: '',
        is_active: true,
        logo: null,
        remove_logo: false,
    };
}

function formFromInstitute(institute: Institute): InstituteForm {
    return {
        code: institute.code,
        name: institute.name,
        phone: institute.phone,
        email: institute.email,
        tin_number: institute.tin_number ?? '',
        bin_number: institute.bin_number ?? '',
        website: institute.website ?? '',
        address: institute.address,
        is_active: institute.is_active,
        logo: null,
        remove_logo: false,
    };
}

function errorMessage(error: unknown): string {
    if (error instanceof ApiRequestError) {
        const validationMessage = error.errors
            ? Object.values(error.errors).flat()[0]
            : null;
        const message = validationMessage ?? error.message;

        return error.status === null
            ? message
            : `HTTP ${error.status}: ${message}`;
    }

    return error instanceof Error ? error.message : 'The request failed.';
}

/** Shows institute information first and keeps editing behind an explicit action. */
export default function InstituteSettings() {
    const { request } = useApi();
    const [institutes, setInstitutes] = useState<InstituteOption[]>([]);
    const [instituteId, setInstituteId] = useState('');
    const [institute, setInstitute] = useState<Institute | null>(null);
    const [form, setForm] = useState<InstituteForm>(emptyForm);
    const [logoPreview, setLogoPreview] = useState<string | null>(null);
    const [editing, setEditing] = useState(false);
    const [managingBranches, setManagingBranches] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    useEffect(() => {
        const controller = new AbortController();

        async function loadInstitutes() {
            try {
                const response = await request<ApiEnvelope<InstituteOption[]>>(
                    '/settings/institutes',
                    { signal: controller.signal },
                );

                if (controller.signal.aborted) {
                    return;
                }

                setInstitutes(response.data.data);
                if (response.data.data.length > 0) {
                    setInstituteId(String(response.data.data[0].id));
                } else {
                    setError('No institute records were found.');
                    setLoading(false);
                }
            } catch (loadError) {
                if (!controller.signal.aborted) {
                    setError(errorMessage(loadError));
                    setLoading(false);
                }
            }
        }

        void loadInstitutes();

        return () => controller.abort();
    }, [request]);

    useEffect(() => {
        if (!instituteId) {
            return;
        }

        const controller = new AbortController();
        setLoading(true);
        setError('');
        setNotice('');

        async function loadInstitute() {
            try {
                const response = await request<ApiEnvelope<Institute>>(
                    `/settings/institutes/${instituteId}`,
                    { signal: controller.signal },
                );

                if (controller.signal.aborted) {
                    return;
                }

                setInstitute(response.data.data);
                setForm(formFromInstitute(response.data.data));
                setEditing(false);
            } catch (loadError) {
                if (!controller.signal.aborted) {
                    setError(errorMessage(loadError));
                }
            } finally {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            }
        }

        void loadInstitute();

        return () => controller.abort();
    }, [instituteId, request]);

    useEffect(() => {
        if (!form.logo) {
            return;
        }

        const objectUrl = URL.createObjectURL(form.logo);
        setLogoPreview(objectUrl);

        return () => URL.revokeObjectURL(objectUrl);
    }, [form.logo]);

    function updateField<K extends keyof InstituteForm>(
        name: K,
        value: InstituteForm[K],
    ) {
        setForm((current) => ({ ...current, [name]: value }));
        setNotice('');
    }

    function textField(
        name: InstituteTextField,
        label: string,
        type = 'text',
        required = false,
    ) {
        return (
            <label
                key={name}
                className="block text-sm font-medium text-slate-600"
            >
                {label}
                {required && <span className="ml-1 text-rose-500">*</span>}
                <input
                    type={type}
                    required={required}
                    value={form[name]}
                    onChange={(event) => updateField(name, event.target.value)}
                    className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 transition outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10"
                />
            </label>
        );
    }

    function pickLogo(event: ChangeEvent<HTMLInputElement>) {
        updateField('logo', event.target.files?.[0] ?? null);
        if (event.target.files?.[0]) {
            updateField('remove_logo', false);
        }
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!instituteId) {
            return;
        }

        setSaving(true);
        setError('');
        setNotice('');

        try {
            const payload = new FormData();
            payload.append('code', form.code);
            payload.append('name', form.name);
            payload.append('phone', form.phone);
            payload.append('email', form.email);
            payload.append('tin_number', form.tin_number);
            payload.append('bin_number', form.bin_number);
            payload.append('website', form.website);
            payload.append('address', form.address);
            payload.append('is_active', form.is_active ? '1' : '0');
            payload.append('remove_logo', form.remove_logo ? '1' : '0');

            if (form.logo) {
                payload.append('logo', form.logo);
            }

            const response = await request<ApiEnvelope<Institute>>(
                `/settings/institutes/${instituteId}`,
                {
                    method: 'POST',
                    headers: { 'X-HTTP-Method-Override': 'PUT' },
                    data: payload,
                },
            );

            setInstitute(response.data.data);
            setForm(formFromInstitute(response.data.data));
            setEditing(false);
            setNotice(response.data.message ?? 'Institute settings saved.');
        } catch (saveError) {
            setError(errorMessage(saveError));
        } finally {
            setSaving(false);
        }
    }

    const activeBranches =
        institute?.branches.filter((branch) => branch.is_active).length ?? 0;
    const displayLogo = form.logo
        ? logoPreview
        : !form.remove_logo
          ? institute?.logo_url
          : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Institute settings | CMUD" />
            <main className="min-h-screen bg-[#F7F9FC] px-4 py-8 text-slate-800 sm:px-7 lg:px-10">
                <div className="mx-auto max-w-6xl space-y-6">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="text-xs font-semibold tracking-[0.16em] text-[#087586] uppercase">
                                Organization settings
                            </p>
                            <h1 className="mt-2 text-3xl font-semibold tracking-tight text-[#17375C]">
                                {managingBranches
                                    ? 'Manage branches'
                                    : 'Institute profile'}
                            </h1>
                            <p className="mt-2 max-w-2xl text-sm text-slate-500">
                                {managingBranches
                                    ? 'Update branch contact details and active status.'
                                    : 'View your institute details and manage its branches.'}
                            </p>
                        </div>
                        {institute && !editing && !managingBranches && (
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setManagingBranches(true);
                                        setError('');
                                        setNotice('');
                                    }}
                                    className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-cyan-200 hover:text-[#087586]"
                                >
                                    <GitBranch size={16} />
                                    Manage branches
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setEditing(true)}
                                    className="inline-flex h-10 items-center gap-2 rounded-xl bg-[#17375C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#254878]"
                                >
                                    <Pencil size={15} />
                                    Edit institute
                                </button>
                            </div>
                        )}
                    </header>

                    {!managingBranches && institutes.length > 1 && (
                        <label className="block max-w-sm text-sm font-medium text-slate-600">
                            Selected institute
                            <select
                                value={instituteId}
                                onChange={(event) => {
                                    setInstituteId(event.target.value);
                                    setManagingBranches(false);
                                }}
                                className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm"
                            >
                                {institutes.map((option) => (
                                    <option key={option.id} value={option.id}>
                                        {option.name} ({option.code})
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}

                    {error && (
                        <div
                            role="alert"
                            className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                        >
                            {error}
                        </div>
                    )}
                    {notice && (
                        <div
                            role="status"
                            className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                        >
                            {notice}
                        </div>
                    )}

                    {loading ? (
                        <div className="flex min-h-56 items-center justify-center rounded-2xl border border-slate-200 bg-white text-sm text-slate-500">
                            <LoaderCircle
                                size={18}
                                className="mr-2 animate-spin"
                            />
                            Loading institute details…
                        </div>
                    ) : institute && managingBranches ? (
                        <BranchManager
                            instituteId={institute.id}
                            instituteName={institute.name}
                            branches={institute.branches}
                            onBranchesChange={(branches) =>
                                setInstitute((current) =>
                                    current
                                        ? { ...current, branches }
                                        : current,
                                )
                            }
                            onClose={() => setManagingBranches(false)}
                        />
                    ) : institute && editing ? (
                        <form onSubmit={submit} className="space-y-5">
                            <div className="grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
                                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                    <div className="mb-6">
                                        <h2 className="text-lg font-semibold text-[#17375C]">
                                            Identity and contact
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            The information shown to staff and
                                            students.
                                        </p>
                                    </div>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        {textField(
                                            'name',
                                            'Institute name',
                                            'text',
                                            true,
                                        )}
                                        {textField(
                                            'code',
                                            'Institute code',
                                            'text',
                                            true,
                                        )}
                                        {textField(
                                            'phone',
                                            'Phone',
                                            'tel',
                                            true,
                                        )}
                                        {textField(
                                            'email',
                                            'Email',
                                            'email',
                                            true,
                                        )}
                                        <label className="block text-sm font-medium text-slate-600 sm:col-span-2">
                                            Address
                                            <textarea
                                                required
                                                value={form.address}
                                                onChange={(event) =>
                                                    updateField(
                                                        'address',
                                                        event.target.value,
                                                    )
                                                }
                                                rows={3}
                                                className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 transition outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10"
                                            />
                                        </label>
                                    </div>
                                </section>

                                <section className="space-y-5">
                                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                        <h2 className="text-lg font-semibold text-[#17375C]">
                                            Registration and web
                                        </h2>
                                        <div className="mt-5 grid gap-4">
                                            {textField(
                                                'tin_number',
                                                'TIN number',
                                            )}
                                            {textField(
                                                'bin_number',
                                                'BIN number',
                                            )}
                                            {textField(
                                                'website',
                                                'Website',
                                                'url',
                                            )}
                                            <label className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600">
                                                Institute status
                                                <select
                                                    value={
                                                        form.is_active
                                                            ? '1'
                                                            : '0'
                                                    }
                                                    onChange={(event) =>
                                                        updateField(
                                                            'is_active',
                                                            event.target
                                                                .value === '1',
                                                        )
                                                    }
                                                    className="rounded-lg border border-slate-200 bg-white px-3 py-2"
                                                >
                                                    <option value="1">
                                                        Active
                                                    </option>
                                                    <option value="0">
                                                        Inactive
                                                    </option>
                                                </select>
                                            </label>
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                        <h2 className="text-lg font-semibold text-[#17375C]">
                                            Institute logo
                                        </h2>
                                        <div className="mt-4 flex flex-wrap items-center gap-4">
                                            {displayLogo && (
                                                <img
                                                    src={displayLogo}
                                                    alt={`${institute.name} logo`}
                                                    className="size-16 rounded-xl border border-slate-200 bg-white object-contain p-1"
                                                />
                                            )}
                                            <label className="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                                <Upload size={15} />
                                                {form.logo
                                                    ? form.logo.name
                                                    : 'Choose logo'}
                                                <input
                                                    type="file"
                                                    accept="image/png,image/jpeg,image/webp"
                                                    onChange={pickLogo}
                                                    className="sr-only"
                                                />
                                            </label>
                                            {(institute.logo_url ||
                                                form.logo) &&
                                                !form.remove_logo && (
                                                    <button
                                                        type="button"
                                                        onClick={() => {
                                                            updateField(
                                                                'logo',
                                                                null,
                                                            );
                                                            updateField(
                                                                'remove_logo',
                                                                true,
                                                            );
                                                        }}
                                                        className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50"
                                                    >
                                                        <X size={15} />
                                                        Remove logo
                                                    </button>
                                                )}
                                            <span className="text-xs text-slate-500">
                                                PNG, JPG, or WebP up to 3 MB.
                                            </span>
                                        </div>
                                    </div>
                                </section>
                            </div>

                            <div className="flex flex-wrap justify-end gap-2">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setForm(formFromInstitute(institute));
                                        setEditing(false);
                                        setError('');
                                    }}
                                    className="h-10 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={saving}
                                    className="inline-flex h-10 items-center gap-2 rounded-xl bg-[#17375C] px-4 text-sm font-semibold text-white transition hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    {saving ? (
                                        <LoaderCircle
                                            size={16}
                                            className="animate-spin"
                                        />
                                    ) : (
                                        <Save size={16} />
                                    )}
                                    {saving ? 'Saving…' : 'Save changes'}
                                </button>
                            </div>
                        </form>
                    ) : institute ? (
                        <div className="space-y-5">
                            <section className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#102D52] to-[#176C7A] p-6 text-white shadow-lg sm:p-8">
                                <div className="absolute -top-20 -right-10 size-64 rounded-full border border-white/10" />
                                <div className="absolute -right-4 -bottom-32 size-72 rounded-full border border-white/10" />
                                <div className="relative flex flex-wrap items-center gap-5">
                                    <div className="grid size-20 shrink-0 place-items-center overflow-hidden rounded-2xl border border-white/20 bg-white/10">
                                        {institute.logo_url ? (
                                            <img
                                                src={institute.logo_url}
                                                alt={`${institute.name} logo`}
                                                className="size-full bg-white object-contain p-2"
                                            />
                                        ) : (
                                            <Building2
                                                size={32}
                                                className="text-cyan-100"
                                            />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-3">
                                            <h2 className="text-2xl font-semibold tracking-tight sm:text-3xl">
                                                {institute.name}
                                            </h2>
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${institute.is_active ? 'bg-emerald-300/20 text-emerald-100' : 'bg-white/15 text-white/80'}`}
                                            >
                                                {institute.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </div>
                                        <p className="mt-2 text-sm text-cyan-50/85">
                                            Institute code{' '}
                                            <span className="font-semibold text-white">
                                                {institute.code}
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div className="relative mt-7 grid gap-3 border-t border-white/15 pt-5 sm:grid-cols-2 lg:grid-cols-3">
                                    <div className="flex items-start gap-3 text-sm text-cyan-50/90">
                                        <Phone
                                            size={16}
                                            className="mt-0.5 shrink-0 text-cyan-200"
                                        />
                                        <span>{institute.phone}</span>
                                    </div>
                                    <div className="flex items-start gap-3 text-sm text-cyan-50/90">
                                        <Mail
                                            size={16}
                                            className="mt-0.5 shrink-0 text-cyan-200"
                                        />
                                        <span className="break-all">
                                            {institute.email}
                                        </span>
                                    </div>
                                    <div className="flex items-start gap-3 text-sm text-cyan-50/90 sm:col-span-2 lg:col-span-1">
                                        <MapPin
                                            size={16}
                                            className="mt-0.5 shrink-0 text-cyan-200"
                                        />
                                        <span>{institute.address}</span>
                                    </div>
                                </div>
                            </section>

                            <div className="grid gap-5 lg:grid-cols-[minmax(0,1.3fr)_minmax(18rem,0.7fr)]">
                                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <p className="text-xs font-semibold tracking-[0.12em] text-[#087586] uppercase">
                                                Organization
                                            </p>
                                            <h2 className="mt-1 text-lg font-semibold text-[#17375C]">
                                                Registration details
                                            </h2>
                                        </div>
                                        <span className="grid size-10 place-items-center rounded-xl bg-cyan-50 text-[#087586]">
                                            <Building2 size={18} />
                                        </span>
                                    </div>
                                    <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                        <InfoItem
                                            label="Tax identification number"
                                            value={institute.tin_number}
                                        />
                                        <InfoItem
                                            label="Business identification number"
                                            value={institute.bin_number}
                                        />
                                        <div className="sm:col-span-2">
                                            <p className="text-xs font-medium text-slate-400">
                                                Website
                                            </p>
                                            {institute.website ? (
                                                <a
                                                    href={institute.website}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="mt-1 inline-flex items-center gap-1.5 text-sm font-medium text-[#087586] hover:underline"
                                                >
                                                    <Globe2 size={15} />
                                                    {institute.website}
                                                    <ExternalLink size={13} />
                                                </a>
                                            ) : (
                                                <p className="mt-1 text-sm text-slate-400">
                                                    Not provided
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                </section>

                                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                                    <div className="flex items-center gap-3">
                                        <span className="grid size-10 place-items-center rounded-xl bg-cyan-50 text-[#087586]">
                                            <GitBranch size={18} />
                                        </span>
                                        <div>
                                            <p className="text-xs font-semibold tracking-[0.12em] text-[#087586] uppercase">
                                                Locations
                                            </p>
                                            <h2 className="text-lg font-semibold text-[#17375C]">
                                                Branches
                                            </h2>
                                        </div>
                                    </div>
                                    <p className="mt-5 text-3xl font-semibold text-[#17375C]">
                                        {institute.branches.length}
                                        <span className="ml-2 text-sm font-medium text-slate-500">
                                            {institute.branches.length === 1
                                                ? 'branch'
                                                : 'branches'}
                                        </span>
                                    </p>
                                    <p className="mt-1 text-sm text-slate-500">
                                        {activeBranches} active locations
                                    </p>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            setManagingBranches(true);
                                            setError('');
                                            setNotice('');
                                        }}
                                        className="mt-5 inline-flex items-center gap-2 text-sm font-semibold text-[#087586] hover:underline"
                                    >
                                        Manage branches
                                        <GitBranch size={15} />
                                    </button>
                                </section>
                            </div>
                        </div>
                    ) : null}
                </div>
            </main>
        </AppLayout>
    );
}

function InfoItem({ label, value }: { label: string; value: string | null }) {
    return (
        <div>
            <p className="text-xs font-medium text-slate-400">{label}</p>
            <p className="mt-1 text-sm font-medium text-slate-700">
                {value || 'Not provided'}
            </p>
        </div>
    );
}
