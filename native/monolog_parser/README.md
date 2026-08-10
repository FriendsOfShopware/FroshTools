# monolog_parser

SIMD Monolog file reader for FroshTools — **prebuilt** shared libs for PHP FFI.
**End users never compile.**

## Cross-compile everything with Zig (recommended)

One machine, six artifacts:

```bash
# zig on PATH, or:  make zig-release ZIG=/path/to/zig
make zig-release

# single target:
make zig-one TARGET=linux-musl-arm64
```

`build-zig.sh` maps:

| Artifact | Zig target |
| --- | --- |
| `libmonolog_parser-linux-x86_64.so` | `x86_64-linux-gnu` |
| `libmonolog_parser-linux-arm64.so` | `aarch64-linux-gnu` |
| `libmonolog_parser-linux-musl-x86_64.so` | `x86_64-linux-musl` |
| `libmonolog_parser-linux-musl-arm64.so` | `aarch64-linux-musl` |
| `libmonolog_parser-darwin-x86_64.dylib` | `x86_64-macos-none` |
| `libmonolog_parser-darwin-arm64.dylib` | `aarch64-macos-none` |

CI (`.github/workflows/native-monolog-parser.yml`) installs Zig 0.13 and runs `make zig-release` on a single `ubuntu-22.04` runner.

## Runtime selection

`NativeLibraryLocator` picks OS + arch + musl/glibc automatically.
Override: `MONOLOG_PARSER_LIB`, `MONOLOG_PARSER_LIBC=musl|gnu`.

## Host-only build (optional)

```bash
make test package          # current OS/arch only
```

## API

```c
monolog_file_read_backwards(path, offset, limit, &page);
// iterator: monolog_reader_open_backwards → next → close
```

See `include/monolog_parser.h`.
