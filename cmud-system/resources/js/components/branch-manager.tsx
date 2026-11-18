import {
    Check,
    GitBranch,
    LoaderCircle,
    MapPin,
    Pencil,
    Plus,
    Save,
    X,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { ApiRequestError, useApi } from '@/hooks/use-api';

type Branch = {
    id: number;
    code: string;
    name: string;
    phone: string;
    email: string | null;
    address: string;
    is_active: boolean;
};

type BranchDraft = Omit<Branch, 'id'> & { id?: number };
type Institute = { branches: Branch[] };
type ApiEnvelope<T> = { data: T; message?: string };

type BranchManagerProps = {
    instituteId: number;
    instituteName: string;
    branches: Branch[];
    onBranchesChange: (branches: Branch[]) => void;
    onClose: () => void;
};

function emptyBranch(): BranchDraft {
    return {
        code: '',
        name: '',
        phone: '',
        email: '',
        address: '',
        is_active: true,
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

export default function BranchManager({
    instituteId,
    instituteName,
    branches,
    onBranchesChange,
    onClose,
}: BranchManagerProps) {
    const { request } = useApi();
    const [draft, setDraft] = useState<BranchDraft>(emptyBranch);
    const [editorOpen, setEditorOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');
    const activeCount = branches.filter((branch) => branch.is_active).length;

    function beginCreate() {
        setDraft(emptyBranch());
        setEditorOpen(true);
        setError('');
        setNotice('');
    }

    function beginEdit(branch: Branch) {
        setDraft({ ...branch });
        setEditorOpen(true);
        setError('');
        setNotice('');
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSaving(true);
        setError('');
        setNotice('');

        const branch = {
            ...(draft.id ? { id: draft.id } : {}),
            code: draft.code.trim(),
            name: draft.name.trim(),
            phone: draft.phone.trim(),
            email: draft.email?.trim() || null,
            address: draft.address.trim(),
            is_active: draft.is_active,
        };

        try {
            const response = await request<ApiEnvelope<Institute>>(
                `/settings/institutes/${instituteId}`,
                {
                    method: 'PUT',
                    data: { branches: [branch] },
                },
            );

            const updatedBranches = response.data.data.branches ?? [];
            onBranchesChange(updatedBranches);
            setNotice(response.data.message ?? 'Branch details saved.');
            setDraft(emptyBranch());
            setEditorOpen(false);
        } catch (saveError) {
            setError(errorMessage(saveError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className="space-y-5">
            <header className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold tracking-[0.16em] text-[#087586] uppercase">
                        {instituteName}
                    </p>
                    <h2 className="mt-2 text-2xl font-semibold tracking-tight text-[#17375C]">
                        Branch directory
                    </h2>
                    <p className="mt-2 text-sm text-slate-500">
                        Manage branch contact information and active status.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={onClose}
                        className="inline-flex h-10 items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-cyan-200 hover:text-[#087586]"
                    >
                        <X size={16} />
                        Back to institute
                    </button>
                    <button
                        type="button"
                        onClick={beginCreate}
                        className="inline-flex h-10 items-center gap-2 rounded-xl bg-[#17375C] px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-[#254878]"
                    >
                        <Plus size={16} />
                        Add branch
                    </button>
                </div>
            </header>

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

            <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(20rem,0.8fr)]">
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header className="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-cyan-50 text-[#087586]">
                                <GitBranch size={18} />
                            </span>
                            <div>
                                <h3 className="font-semibold text-[#17375C]">
                                    {instituteName}
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Branch records
                                </p>
                            </div>
                        </div>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {branches.length} total · {activeCount} active
                        </span>
                    </header>

                    {branches.length === 0 ? (
                        <div className="px-6 py-14 text-center">
                            <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-slate-100 text-slate-500">
                                <MapPin size={21} />
                            </span>
                            <h3 className="mt-4 font-semibold text-[#17375C]">
                                No branches yet
                            </h3>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                                Add the first branch to maintain its contact
                                details and status here.
                            </p>
                            <button
                                type="button"
                                onClick={beginCreate}
                                className="mt-5 inline-flex items-center gap-2 rounded-xl bg-[#17375C] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#254878]"
                            >
                                <Plus size={15} />
                                Add first branch
                            </button>
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {branches.map((branch) => (
                                <article
                                    key={branch.id}
                                    className="flex flex-wrap items-start justify-between gap-4 p-5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <h3 className="font-semibold text-[#17375C]">
                                                {branch.name}
                                            </h3>
                                            <StatusBadge
                                                active={branch.is_active}
                                            />
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {branch.code}
                                        </p>
                                        <div className="mt-3 grid gap-x-6 gap-y-1 text-sm text-slate-600 sm:grid-cols-2">
                                            <p>{branch.phone}</p>
                                            {branch.email && (
                                                <p className="break-all">
                                                    {branch.email}
                                                </p>
                                            )}
                                            <p className="mt-1 text-xs leading-5 text-slate-500 sm:col-span-2">
                                                {branch.address}
                                            </p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => beginEdit(branch)}
                                        className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold text-[#087586] hover:bg-cyan-50"
                                    >
                                        <Pencil size={14} />
                                        Edit
                                    </button>
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    {editorOpen ? (
                        <>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-xs font-semibold tracking-[0.12em] text-[#087586] uppercase">
                                        Branch details
                                    </p>
                                    <h3 className="mt-1 text-lg font-semibold text-[#17375C]">
                                        {draft.id
                                            ? 'Update branch'
                                            : 'Add branch'}
                                    </h3>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setEditorOpen(false)}
                                    aria-label="Close branch editor"
                                    className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <X size={17} />
                                </button>
                            </div>
                            <form onSubmit={submit} className="mt-5 space-y-4">
                                <TextField
                                    label="Branch name"
                                    value={draft.name}
                                    required
                                    onChange={(value) =>
                                        setDraft((current) => ({
                                            ...current,
                                            name: value,
                                        }))
                                    }
                                />
                                <TextField
                                    label="Branch code"
                                    value={draft.code}
                                    required
                                    onChange={(value) =>
                                        setDraft((current) => ({
                                            ...current,
                                            code: value,
                                        }))
                                    }
                                />
                                <TextField
                                    label="Phone"
                                    value={draft.phone}
                                    required
                                    onChange={(value) =>
                                        setDraft((current) => ({
                                            ...current,
                                            phone: value,
                                        }))
                                    }
                                />
                                <TextField
                                    label="Email"
                                    type="email"
                                    value={draft.email ?? ''}
                                    onChange={(value) =>
                                        setDraft((current) => ({
                                            ...current,
                                            email: value,
                                        }))
                                    }
                                />
                                <label className="block text-sm font-medium text-slate-600">
                                    Address
                                    <textarea
                                        required
                                        rows={3}
                                        value={draft.address}
                                        onChange={(event) =>
                                            setDraft((current) => ({
                                                ...current,
                                                address: event.target.value,
                                            }))
                                        }
                                        className="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10"
                                    />
                                </label>
                                <label className="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm font-medium text-slate-600">
                                    Branch status
                                    <select
                                        value={draft.is_active ? '1' : '0'}
                                        onChange={(event) =>
                                            setDraft((current) => ({
                                                ...current,
                                                is_active:
                                                    event.target.value === '1',
                                            }))
                                        }
                                        className="rounded-lg border border-slate-200 bg-white px-3 py-2"
                                    >
                                        <option value="1">Active</option>
                                        <option value="0">Inactive</option>
                                    </select>
                                </label>
                                <div className="flex justify-end gap-2 pt-2">
                                    <button
                                        type="button"
                                        onClick={() => setEditorOpen(false)}
                                        className="h-10 rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={saving}
                                        className="inline-flex h-10 items-center gap-2 rounded-xl bg-[#17375C] px-4 text-sm font-semibold text-white hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        {saving ? (
                                            <LoaderCircle
                                                size={15}
                                                className="animate-spin"
                                            />
                                        ) : (
                                            <Save size={15} />
                                        )}
                                        {saving
                                            ? 'Saving…'
                                            : draft.id
                                              ? 'Save branch'
                                              : 'Create branch'}
                                    </button>
                                </div>
                            </form>
                        </>
                    ) : (
                        <div className="py-5 text-center">
                            <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-cyan-50 text-[#087586]">
                                <Pencil size={20} />
                            </span>
                            <h3 className="mt-4 font-semibold text-[#17375C]">
                                Branch editor
                            </h3>
                            <p className="mt-2 text-sm leading-6 text-slate-500">
                                Choose a branch to update its details, or add a
                                new branch to this institute.
                            </p>
                            <button
                                type="button"
                                onClick={beginCreate}
                                className="mt-5 inline-flex items-center gap-2 rounded-xl bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-[#087586] transition hover:bg-cyan-100"
                            >
                                <Plus size={15} />
                                Add a branch
                            </button>
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
}

function TextField({
    label,
    value,
    type = 'text',
    required = false,
    onChange,
}: {
    label: string;
    value: string;
    type?: string;
    required?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <label className="block text-sm font-medium text-slate-600">
            {label}
            {required && <span className="ml-1 text-rose-500">*</span>}
            <input
                type={type}
                required={required}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-2 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none focus:border-[#16828D] focus:ring-3 focus:ring-[#16828D]/10"
            />
        </label>
    );
}

function StatusBadge({ active }: { active: boolean }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'}`}
        >
            {active ? <Check size={12} /> : <X size={12} />}
            {active ? 'Active' : 'Inactive'}
        </span>
    );
}
