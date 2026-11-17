import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, ChevronDown, LoaderCircle, Plus, Trash2, UserPlus } from 'lucide-react';
import { useMemo, useState, type FormEvent, type ReactNode } from 'react';

type BranchOption = { id: number; institute_id: number; name: string };
type BatchOption = {
    id: number;
    course_id: number;
    branch_id: number;
    name: string;
    starts_on: string;
    status: string;
    course: { name: string };
};
type MedicalCollegeOption = { id: number; name: string };

type Props = {
    branches: BranchOption[];
    batches: BatchOption[];
    medicalColleges: MedicalCollegeOption[];
};

type AdmissionForm = {
    student_code: string;
    admission_branch_id: string;
    batch_id: string;
    admission_date: string;
    full_name: string;
    father_name: string;
    mother_name: string;
    date_of_birth: string;
    gender: string;
    phone_numbers: string[];
    email: string;
    present_address: string;
    permanent_address: string;
    medical_college_id: string;
    qualification: string;
    graduation_year: string;
    bmdc_number: string;
    notes: string;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Academy', href: '/academy/students' },
    { title: 'Students', href: '/academy/students' },
    { title: 'New admission', href: '/academy/students/admission' },
];

function initialForm(): AdmissionForm {
    return {
        student_code: '', admission_branch_id: '', batch_id: '', admission_date: '',
        full_name: '', father_name: '', mother_name: '', date_of_birth: '', gender: '',
        phone_numbers: [''], email: '', present_address: '', permanent_address: '',
        medical_college_id: '', qualification: '', graduation_year: '', bmdc_number: '', notes: '',
    };
}

export default function StudentAdmission({ branches, batches, medicalColleges }: Props) {
    const [form, setForm] = useState(initialForm);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);
    const [requestError, setRequestError] = useState('');
    const [createdStudentCode, setCreatedStudentCode] = useState('');
    const [successMessage, setSuccessMessage] = useState('');

    const availableBatches = useMemo(() => batches.filter((batch) =>
        String(batch.branch_id) === form.admission_branch_id,
    ), [batches, form.admission_branch_id]);

    function update<K extends keyof AdmissionForm>(field: K, value: AdmissionForm[K]) {
        setForm((current) => ({ ...current, [field]: value }));
        setErrors((current) => ({ ...current, [field]: '' }));
        setRequestError('');
        setCreatedStudentCode('');
        setSuccessMessage('');
    }

    function updatePhoneNumber(index: number, value: string) {
        setForm((current) => ({
            ...current,
            phone_numbers: current.phone_numbers.map((phone, phoneIndex) => phoneIndex === index ? value : phone),
        }));
        setErrors((current) => ({ ...current, phone_numbers: '', [`phone_numbers.${index}`]: '' }));
        setRequestError('');
        setCreatedStudentCode('');
        setSuccessMessage('');
    }

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        setRequestError('');
        setCreatedStudentCode('');
        setSuccessMessage('');

        const payload = {
            student_code: form.student_code.trim(),
            admission_branch_id: Number(form.admission_branch_id),
            batch_id: Number(form.batch_id),
            admission_date: form.admission_date || null,
            full_name: form.full_name.trim(),
            phone_numbers: form.phone_numbers.map((phone) => phone.trim()).filter(Boolean),
            medical_college_id: form.medical_college_id ? Number(form.medical_college_id) : null,
            graduation_year: form.graduation_year ? Number(form.graduation_year) : null,
            date_of_birth: form.date_of_birth || null,
            father_name: form.father_name || null,
            mother_name: form.mother_name || null,
            gender: form.gender || null,
            email: form.email || null,
            present_address: form.present_address || null,
            permanent_address: form.permanent_address || null,
            qualification: form.qualification || null,
            bmdc_number: form.bmdc_number || null,
            notes: form.notes || null,
        };

        try {
            const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
            const response = await fetch('/academy/students/admissions', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });
            const result = await response.json().catch(() => null) as {
                status?: 'success' | 'failure';
                data?: { student?: { student_code?: string; full_name?: string } };
                message?: string;
                errors?: Record<string, string[]>;
            } | null;

            if (!response.ok) {
                if (response.status === 422 && result?.errors) {
                    setErrors(Object.fromEntries(Object.entries(result.errors).map(([key, messages]) => [key, messages[0] ?? 'Invalid value.'])));
                    setRequestError(result?.message ?? 'Please correct the highlighted fields and try again.');
                } else if (response.status === 401 || response.status === 403) {
                    setRequestError(result?.message ?? 'You are not authorized to submit an admission.');
                } else {
                    setRequestError(result?.message ?? 'The admission could not be submitted. Please try again.');
                }
                return;
            }

            if (result?.status !== 'success') {
                setRequestError(result?.message ?? 'The admission could not be submitted. Please try again.');
                return;
            }

            const studentCode = result?.data?.student?.student_code;
            if (!studentCode) {
                setRequestError('The server created a response without a student code. Please check the student list before retrying.');
                return;
            }

            setCreatedStudentCode(studentCode);
            setSuccessMessage(result.message ?? 'Student admission submitted successfully.');
            setForm(initialForm());
        } catch {
            setRequestError('Could not reach the admission service. Check your connection and try again.');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New student admission | CMUD Academy" />
            <main className="min-h-screen bg-[#F8F9FA] px-4 py-6 text-[#2C3E50] sm:px-7 lg:px-10">
                <div className="mx-auto max-w-5xl space-y-6">
                    <header className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <Link href="/academy/students" className="mb-4 inline-flex items-center gap-2 text-xs font-semibold text-[#008295] hover:underline"><ArrowLeft size={14} /> Back to students</Link>
                            <div className="flex items-center gap-3"><span className="grid h-11 w-11 place-items-center rounded-xl bg-[#E5F3F4] text-[#008295]"><UserPlus size={20} /></span><div><p className="text-xs font-bold tracking-[0.12em] text-[#008295] uppercase">Students &amp; Academy</p><h1 className="mt-1 text-3xl font-semibold tracking-tight text-[#1B365D]">New student admission</h1></div></div>
                            <p className="mt-3 text-sm text-slate-500">Register a student and enroll them in their first course.</p>
                        </div>
                    </header>

                    {createdStudentCode && <div role="status" className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><span className="inline-flex items-center gap-2"><CheckCircle2 size={18} /> {successMessage} Student ID: <strong className="font-mono">{createdStudentCode}</strong></span><Link href="/academy/students" className="font-semibold underline">View student list</Link></div>}
                    {requestError && <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{requestError}</div>}

                    <form onSubmit={submit} noValidate className="space-y-6">
                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                            <SectionHeading number="01" title="Admission and enrollment" description="Choose the required branch and batch. The batch determines the course." />
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <SelectField label="Branch" name="admission_branch_id" value={form.admission_branch_id} required error={errors.admission_branch_id} onChange={(value) => { update('admission_branch_id', value); update('batch_id', ''); }}>
                                    <option value="">Select branch</option>{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}
                                </SelectField>
                                <SelectField label="Batch" name="batch_id" value={form.batch_id} required error={errors.batch_id} onChange={(value) => update('batch_id', value)} disabled={!form.admission_branch_id}>
                                    <option value="">{form.admission_branch_id ? 'Select batch' : 'Select a branch first'}</option>{availableBatches.map((batch) => <option key={batch.id} value={batch.id}>{batch.name} · {batch.course.name} · starts {batch.starts_on}</option>)}
                                </SelectField>
                                <InputField label="Admission date" name="admission_date" type="date" value={form.admission_date} error={errors.admission_date} onChange={(value) => update('admission_date', value)} />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                            <SectionHeading number="02" title="Student information" description="Enter the student's contact and personal information." />
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <InputField label="Student ID (student code)" name="student_code" value={form.student_code} required error={errors.student_code} onChange={(value) => update('student_code', value)} />
                                <InputField label="Full name" name="full_name" value={form.full_name} required error={errors.full_name} onChange={(value) => update('full_name', value)} />
                                <div className="sm:col-span-2">
                                    <p className="mb-1.5 text-xs font-semibold text-slate-600">Phone number<span className="ml-1 text-rose-500">*</span></p>
                                    <div className="space-y-3">
                                        {form.phone_numbers.map((phone, index) => <div key={index} className="flex items-start gap-2">
                                            <div className="flex-1"><input id={`phone-number-${index}`} type="tel" value={phone} required={index === 0} onChange={(event) => updatePhoneNumber(index, event.target.value)} aria-label={`Phone number ${index + 1}`} aria-invalid={Boolean(errors[`phone_numbers.${index}`] || (index === 0 && errors.phone_numbers))} placeholder="01XXXXXXXXX" className={`h-11 w-full rounded-xl border bg-white px-3 text-sm outline-none transition focus:border-[#008295] focus:ring-3 focus:ring-[#008295]/10 ${errors[`phone_numbers.${index}`] || (index === 0 && errors.phone_numbers) ? 'border-rose-300' : 'border-slate-200'}`} />
                                                {(errors[`phone_numbers.${index}`] || (index === 0 && errors.phone_numbers)) && <p className="mt-1 text-xs text-rose-600">{errors[`phone_numbers.${index}`] || errors.phone_numbers}</p>}
                                            </div>
                                            <button type="button" onClick={() => setForm((current) => ({ ...current, phone_numbers: current.phone_numbers.filter((_, phoneIndex) => phoneIndex !== index) }))} disabled={form.phone_numbers.length === 1} aria-label={`Remove phone number ${index + 1}`} className="grid h-11 w-10 place-items-center rounded-xl border border-slate-200 text-slate-400 hover:bg-slate-50 disabled:opacity-40"><Trash2 size={15} /></button>
                                        </div>)}
                                    </div>
                                    <button type="button" onClick={() => setForm((current) => ({ ...current, phone_numbers: [...current.phone_numbers, ''] }))} className="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-[#008295] hover:underline"><Plus size={14} /> Add another phone</button>
                                    {errors.phone_numbers && !form.phone_numbers.length && <p className="mt-1 text-xs text-rose-600">{errors.phone_numbers}</p>}
                                </div>
                                <InputField label="Father's name" name="father_name" value={form.father_name} error={errors.father_name} onChange={(value) => update('father_name', value)} />
                                <InputField label="Mother's name" name="mother_name" value={form.mother_name} error={errors.mother_name} onChange={(value) => update('mother_name', value)} />
                                <InputField label="Date of birth" name="date_of_birth" type="date" value={form.date_of_birth} error={errors.date_of_birth} onChange={(value) => update('date_of_birth', value)} />
                                <SelectField label="Gender" name="gender" value={form.gender} error={errors.gender} onChange={(value) => update('gender', value)}><option value="">Select gender</option><option value="female">Female</option><option value="male">Male</option><option value="other">Other</option></SelectField>
                                <InputField label="Email" name="email" type="email" value={form.email} error={errors.email} onChange={(value) => update('email', value)} />
                                <TextField label="Present address" name="present_address" value={form.present_address} error={errors.present_address} onChange={(value) => update('present_address', value)} />
                                <TextField label="Permanent address" name="permanent_address" value={form.permanent_address} error={errors.permanent_address} onChange={(value) => update('permanent_address', value)} />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                            <SectionHeading number="03" title="Qualification" description="Record professional education and registration details." />
                            <div className="mt-6 grid gap-5 sm:grid-cols-2">
                                <InputField label="Qualification" name="qualification" value={form.qualification} error={errors.qualification} onChange={(value) => update('qualification', value)} />
                                <InputField label="Graduation year" name="graduation_year" type="number" min="1900" max="2100" value={form.graduation_year} error={errors.graduation_year} onChange={(value) => update('graduation_year', value)} />
                                <InputField label="BMDC number" name="bmdc_number" value={form.bmdc_number} error={errors.bmdc_number} onChange={(value) => update('bmdc_number', value)} />
                                <SelectField label="Medical college" name="medical_college_id" value={form.medical_college_id} error={errors.medical_college_id} onChange={(value) => update('medical_college_id', value)}><option value="">Select medical college (optional)</option>{medicalColleges.map((college) => <option key={college.id} value={college.id}>{college.name}</option>)}</SelectField>
                                <TextField label="Notes" name="notes" value={form.notes} error={errors.notes} onChange={(value) => update('notes', value)} />
                            </div>
                        </section>

                        <div className="flex flex-wrap items-center justify-end gap-3 pb-6">
                            <Link href="/academy/students" className="rounded-xl px-4 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</Link>
                            <button type="submit" disabled={submitting} className="inline-flex h-11 items-center gap-2 rounded-xl bg-[#1B365D] px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#254878] disabled:cursor-not-allowed disabled:opacity-60">
                                {submitting ? <LoaderCircle size={16} className="animate-spin" /> : <UserPlus size={16} />}
                                {submitting ? 'Submitting…' : 'Submit'}
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </AppLayout>
    );
}

function SectionHeading({ number, title, description }: { number: string; title: string; description: string }) {
    return <div className="flex items-start gap-3"><span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-[#E5F3F4] text-xs font-bold text-[#008295]">{number}</span><div><h2 className="text-base font-semibold text-[#1B365D]">{title}</h2><p className="mt-1 text-xs text-slate-500">{description}</p></div></div>;
}

function FieldLabel({ label, required, htmlFor }: { label: string; required?: boolean; htmlFor: string }) {
    return <label htmlFor={htmlFor} className="mb-1.5 block text-xs font-semibold text-slate-600">{label}{required && <span className="ml-1 text-rose-500">*</span>}</label>;
}

function InputField({ label, name, value, type = 'text', required, error, onChange, min, max }: { label: string; name: string; value: string; type?: string; required?: boolean; error?: string; onChange: (value: string) => void; min?: string; max?: string }) {
    return <div><FieldLabel label={label} required={required} htmlFor={name} /><input id={name} name={name} type={type} value={value} required={required} min={min} max={max} onChange={(event) => onChange(event.target.value)} aria-invalid={Boolean(error)} className={`h-11 w-full rounded-xl border bg-white px-3 text-sm outline-none transition focus:border-[#008295] focus:ring-3 focus:ring-[#008295]/10 ${error ? 'border-rose-300' : 'border-slate-200'}`} />{error && <p className="mt-1 text-xs text-rose-600">{error}</p>}</div>;
}

function TextField({ label, name, value, required, error, onChange }: { label: string; name: string; value: string; required?: boolean; error?: string; onChange: (value: string) => void }) {
    return <div><FieldLabel label={label} required={required} htmlFor={name} /><textarea id={name} name={name} value={value} required={required} rows={3} onChange={(event) => onChange(event.target.value)} aria-invalid={Boolean(error)} className={`w-full resize-y rounded-xl border bg-white px-3 py-2.5 text-sm outline-none transition focus:border-[#008295] focus:ring-3 focus:ring-[#008295]/10 ${error ? 'border-rose-300' : 'border-slate-200'}`} />{error && <p className="mt-1 text-xs text-rose-600">{error}</p>}</div>;
}

function SelectField({ label, name, value, required, error, disabled, onChange, children }: { label: string; name: string; value: string; required?: boolean; error?: string; disabled?: boolean; onChange: (value: string) => void; children: ReactNode }) {
    return <div><FieldLabel label={label} required={required} htmlFor={name} /><div className="relative"><select id={name} name={name} value={value} required={required} disabled={disabled} onChange={(event) => onChange(event.target.value)} aria-invalid={Boolean(error)} className={`h-11 w-full appearance-none rounded-xl border bg-white px-3 pr-9 text-sm outline-none transition focus:border-[#008295] focus:ring-3 focus:ring-[#008295]/10 disabled:bg-slate-50 disabled:text-slate-400 ${error ? 'border-rose-300' : 'border-slate-200'}`}>{children}</select><ChevronDown className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-slate-400" size={15} /></div>{error && <p className="mt-1 text-xs text-rose-600">{error}</p>}</div>;
}
