import Image, { type ImageProps } from "next/image";

/**
 * next/image wrapper: local assets are optimised; absolute URLs coming from the
 * API (storage on another host) are passed through unoptimised so no
 * remotePatterns change is needed per environment.
 */
export function AppImage(props: ImageProps) {
  const src = typeof props.src === "string" ? props.src : "";
  const remote = /^https?:\/\//.test(src);
  return <Image {...props} unoptimized={props.unoptimized ?? remote} alt={props.alt} />;
}
