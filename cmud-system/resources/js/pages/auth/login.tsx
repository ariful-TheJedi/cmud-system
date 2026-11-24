import { Form, Head, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

/** Login form using the shared institute logo and CMUD workspace styling. */
export default function Login({ status, canResetPassword }: Props) {
    const { branding } = usePage().props;

    return (
        <>
            <Head title="Log in" />
            <main className="flex min-h-svh items-center justify-center bg-slate-50 px-4 py-10 dark:bg-slate-950 sm:px-6">
                <section className="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-9">
                    <header className="mb-8 flex flex-col items-center text-center">
                        <div className="mb-5 flex size-16 items-center justify-center rounded-xl border border-slate-200 bg-white p-2 dark:border-slate-700">
                            <img src={branding?.logo_url ?? '/apple-touch-icon.png'} alt={`${branding?.name ?? 'CMUD'} logo`} className="size-full object-contain" />
                        </div>
                        <h1 className="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white">User Login</h1>
                        <p className="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Sign in with your account email and password.</p>
                    </header>

                    <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-5">
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="email" className="text-slate-700 dark:text-slate-200">Email address</Label>
                                    <Input id="email" type="email" name="email" required autoFocus autoComplete="username" placeholder="you@example.com" className="h-11 rounded-lg border-slate-300 bg-white px-3 shadow-none placeholder:text-slate-400 focus-visible:border-teal-700 focus-visible:ring-teal-700/20 dark:border-slate-700 dark:bg-slate-950" />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <div className="flex items-center justify-between gap-3">
                                        <Label htmlFor="password" className="text-slate-700 dark:text-slate-200">Password</Label>
                                        {canResetPassword && <TextLink href={request()} className="text-sm font-medium text-teal-700 hover:text-teal-800 dark:text-teal-400">Forgot password?</TextLink>}
                                    </div>
                                    <PasswordInput id="password" name="password" required autoComplete="current-password" placeholder="Enter your password" className="h-11 rounded-lg border-slate-300 bg-white px-3 shadow-none placeholder:text-slate-400 focus-visible:border-teal-700 focus-visible:ring-teal-700/20 dark:border-slate-700 dark:bg-slate-950" />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="flex items-center gap-3">
                                    <Checkbox id="remember" name="remember" className="rounded-md" />
                                    <Label htmlFor="remember" className="cursor-pointer text-sm font-normal text-slate-600 dark:text-slate-300">Keep me logged in</Label>
                                </div>

                                <Button type="submit" disabled={processing} className="mt-1 h-11 w-full rounded-lg bg-[#17375C] text-sm font-semibold text-white shadow-none hover:bg-[#254878]" data-test="login-button">
                                    {processing ? <Spinner /> : <>Sign in <ArrowRight className="size-4" /></>}
                                </Button>
                            </>
                        )}
                    </Form>

                    {status && <div role="status" className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{status}</div>}

                </section>
            </main>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Sign in with your account email and password',
};
