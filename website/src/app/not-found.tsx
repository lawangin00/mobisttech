import Link from "next/link";

export default function NotFound() {
  return (
    <main className="mx-auto max-w-3xl px-4 py-24 text-center">
      <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">Not available</p>
      <h1 className="mt-3 text-4xl font-bold">This page is not active.</h1>
      <p className="mt-4 text-slate-600">
        The current Website operating mode or published catalogue does not expose this route.
      </p>
      <Link href="/" className="mt-7 inline-block rounded-full bg-slate-950 px-5 py-3 font-semibold text-white">
        Return home
      </Link>
    </main>
  );
}
