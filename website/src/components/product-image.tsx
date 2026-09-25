import Image from "next/image";

export function ProductImage({
  src,
  alt,
  priority = false,
  ratio = "4:3",
}: {
  src: string | null;
  alt: string;
  priority?: boolean;
  ratio?: "16:9" | "4:3" | "1:1";
}) {
  const safe = typeof src === "string" && src.startsWith("/") && !src.startsWith("//");

  return (
    <div className={`relative overflow-hidden rounded-2xl bg-slate-100 ${ratio === "1:1" ? "aspect-square" : ratio === "16:9" ? "aspect-video" : "aspect-[4/3]"}`}>
      {safe ? (
        <Image
          src={src}
          alt={alt}
          fill
          sizes="(max-width: 768px) 100vw, 33vw"
          priority={priority}
          className="object-contain p-4"
        />
      ) : (
        <div className="flex h-full items-center justify-center px-6 text-center text-sm text-slate-400">
          Product image unavailable
        </div>
      )}
    </div>
  );
}
