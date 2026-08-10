# Prebuilt `libmonolog_parser` (Zig cross-compile)

| File | Platform | libc |
| --- | --- | --- |
| `libmonolog_parser-linux-x86_64.so` | Linux amd64 | glibc |
| `libmonolog_parser-linux-arm64.so` | Linux arm64 | glibc |
| `libmonolog_parser-linux-musl-x86_64.so` | Linux amd64 | musl |
| `libmonolog_parser-linux-musl-arm64.so` | Linux arm64 | musl |
| `libmonolog_parser-darwin-x86_64.dylib` | macOS Intel | — |
| `libmonolog_parser-darwin-arm64.dylib` | macOS Apple Silicon | — |

## Rebuild all (one command)

```bash
cd ../monolog_parser
make zig-release          # needs zig ≥ 0.13
# or: make zig-release ZIG=/tmp/zig-linux-x86_64-0.13.0/zig
```

Produced here with Zig 0.13 from a single Linux host — no Alpine/macOS runners required.

## Runtime

PHP `NativeLibraryLocator` auto-selects; users need `ext-ffi` only (optional).
