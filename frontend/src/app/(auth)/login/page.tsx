"use client";

import {
  CalendarCheck,
  Eye,
  EyeOff,
  GraduationCap,
  LoaderCircle,
  Lock,
  Mail,
  NotebookPen,
  UsersRound,
} from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import { useRouter } from "next/navigation";
import { ThemeToggle } from "@/components/ThemeToggle";
import { useAuth } from "@/features/auth/AuthContext";
import { homePathFor } from "@/lib/audience";
import { ApiRequestError } from "@/services/apiClient";

const HIGHLIGHTS = [
  {
    icon: UsersRound,
    title: "Students and guardians",
    text: "Admissions, enrollments, ID cards and certificates.",
  },
  {
    icon: CalendarCheck,
    title: "Attendance and routines",
    text: "Daily attendance and clash-free class routines.",
  },
  {
    icon: NotebookPen,
    title: "Exams and results",
    text: "Exam schedules, marks and grade scales.",
  },
];

export default function LoginPage() {
  const { login, user, loading } = useAuth();
  const router = useRouter();

  // Already signed in? Go straight to this user's area.
  useEffect(() => {
    if (!loading && user) router.replace(homePathFor(user.audience));
  }, [loading, user, router]);
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);

    try {
      const signedIn = await login(email, password);
      router.replace(homePathFor(signedIn.audience));
    } catch (err) {
      if (err instanceof ApiRequestError) {
        const fieldError = err.error.errors
          ? Object.values(err.error.errors)[0]?.[0]
          : undefined;
        setError(fieldError ?? err.error.message);
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main className="grid min-h-screen lg:grid-cols-[1.1fr_1fr]">
      {/* Brand panel */}
      <section className="from-brand-600 relative hidden overflow-hidden bg-linear-to-br via-indigo-600 to-violet-700 p-12 text-white lg:flex lg:flex-col">
        <span
          aria-hidden
          className="absolute -top-24 -left-24 h-80 w-80 rounded-full bg-white/10"
        />
        <span
          aria-hidden
          className="absolute -right-16 bottom-24 h-64 w-64 rounded-full bg-white/10"
        />
        <span
          aria-hidden
          className="absolute right-1/3 -bottom-24 h-56 w-56 rounded-full bg-violet-400/20"
        />

        <div className="relative flex items-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/25">
            <GraduationCap className="h-6 w-6" />
          </span>
          <span className="text-lg font-bold tracking-tight">
            School Management
          </span>
        </div>

        <div className="relative my-auto max-w-lg">
          <h2 className="text-4xl leading-tight font-bold tracking-tight">
            Run your whole school from one place.
          </h2>
          <p className="mt-4 text-lg text-white/80">
            Enter it once, use it everywhere: for the office, teachers, parents
            and students.
          </p>
          <ul className="mt-10 space-y-5">
            {HIGHLIGHTS.map(({ icon: Icon, title, text }) => (
              <li key={title} className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                  <Icon className="h-5 w-5" />
                </span>
                <span>
                  <span className="block font-semibold">{title}</span>
                  <span className="block text-sm text-white/75">{text}</span>
                </span>
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-sm text-white/60">
          © {new Date().getFullYear()} School Management System
        </p>
      </section>

      {/* Sign-in form */}
      <section className="relative flex items-center justify-center px-6 py-12">
        <div className="absolute top-4 right-4">
          <ThemeToggle />
        </div>
        <div className="w-full max-w-sm">
          <div className="mb-8 flex items-center gap-3 lg:hidden">
            <span className="from-brand-500 shadow-brand-600/30 flex h-11 w-11 items-center justify-center rounded-xl bg-linear-to-br to-indigo-600 text-white shadow-lg">
              <GraduationCap className="h-6 w-6" />
            </span>
            <span className="text-lg font-bold tracking-tight">
              School Management
            </span>
          </div>

          <h1 className="text-3xl font-bold">Welcome back</h1>
          <p className="mt-2 mb-8 text-sm text-slate-500 dark:text-slate-400">
            Sign in to your account to continue.
          </p>

          <form onSubmit={handleSubmit} className="space-y-5" noValidate>
            <div>
              <label
                htmlFor="email"
                className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
              >
                Email
              </label>
              <div className="relative">
                <Mail className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  id="email"
                  type="email"
                  autoComplete="email"
                  required
                  placeholder="you@school.edu"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="input w-full py-2.5 pl-10"
                />
              </div>
            </div>

            <div>
              <label
                htmlFor="password"
                className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300"
              >
                Password
              </label>
              <div className="relative">
                <Lock className="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  id="password"
                  type={showPassword ? "text" : "password"}
                  autoComplete="current-password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="input w-full py-2.5 pr-10 pl-10"
                />
                <button
                  type="button"
                  onClick={() => setShowPassword((v) => !v)}
                  aria-label={showPassword ? "Hide password" : "Show password"}
                  className="absolute top-1/2 right-2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
                >
                  {showPassword ? (
                    <EyeOff className="h-4 w-4" />
                  ) : (
                    <Eye className="h-4 w-4" />
                  )}
                </button>
              </div>
            </div>

            {error && (
              <p role="alert" className="alert alert-error">
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={submitting}
              className="btn btn-primary w-full py-2.5 text-[15px]"
            >
              {submitting && <LoaderCircle className="h-4 w-4 animate-spin" />}
              {submitting ? "Signing in…" : "Sign in"}
            </button>
          </form>
        </div>
      </section>
    </main>
  );
}
