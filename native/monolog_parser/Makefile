# monolog_parser — SIMD Monolog library
#
# Native (host toolchain):
#   make / make test / make package
#
# Cross-compile ALL release targets with Zig (preferred for CI + local):
#   make zig-release          # needs `zig` on PATH or ZIG=/path/to/zig
#   make zig-release ZIG=...
#
# Artifacts → ../lib/
#   libmonolog_parser-linux-x86_64.so
#   libmonolog_parser-linux-arm64.so
#   libmonolog_parser-linux-musl-x86_64.so
#   libmonolog_parser-linux-musl-arm64.so
#   libmonolog_parser-darwin-x86_64.dylib
#   libmonolog_parser-darwin-arm64.dylib

CC       ?= cc
CFLAGS   ?= -O3 -fPIC -Wall -Wextra -Wpedantic -std=c11
CFLAGS   += -Iinclude
ZIG      ?= zig

UNAME_S  := $(shell uname -s)
UNAME_M  := $(shell uname -m)

ARCH_RAW := $(UNAME_M)
ifeq ($(ARCH_RAW),amd64)
  ARCH := x86_64
else ifeq ($(ARCH_RAW),x86_64)
  ARCH := x86_64
else ifeq ($(ARCH_RAW),aarch64)
  ARCH := arm64
else ifeq ($(ARCH_RAW),arm64)
  ARCH := arm64
else
  ARCH := $(ARCH_RAW)
endif

ifndef LIBC
  ifneq ($(wildcard /lib/ld-musl-*.so.1),)
    LIBC := musl
  else
    LIBC := gnu
  endif
endif

ifeq ($(UNAME_S),Darwin)
  OS       := darwin
  LIBEXT   := dylib
  LIBC     := n/a
  LDFLAGS  += -dynamiclib -install_name @rpath/libmonolog_parser.$(LIBEXT)
  CFLAGS   += -mmacosx-version-min=11.0
  TEST_RPATH := -Wl,-rpath,@loader_path/..
  LIB_PACKAGED := libmonolog_parser-darwin-$(ARCH).$(LIBEXT)
else
  OS       := linux
  LIBEXT   := so
  LDFLAGS  += -shared
  TEST_RPATH := -Wl,-rpath,'$$ORIGIN/..'
  ifeq ($(LIBC),musl)
    LIB_PACKAGED := libmonolog_parser-linux-musl-$(ARCH).$(LIBEXT)
  else
    LIB_PACKAGED := libmonolog_parser-linux-$(ARCH).$(LIBEXT)
  endif
endif

ifeq ($(ARCH),arm64)
  ifneq ($(UNAME_S),Darwin)
    CFLAGS += -march=armv8-a+simd
  endif
endif

SRC      := src/simd_scan.c src/monolog_parser.c src/monolog_reader.c
OBJ      := $(SRC:.c=.o)

LIB_LOCAL   := libmonolog_parser.$(LIBEXT)
INSTALL_DIR ?= ../lib

.PHONY: all clean test bench install package shared info zig-release zig-one

all: shared

info:
	@echo "os=$(OS) arch=$(ARCH) libc=$(LIBC) ext=$(LIBEXT)"
	@echo "local=$(LIB_LOCAL) packaged=$(LIB_PACKAGED)"
	@echo "zig=$(ZIG)"

shared: $(LIB_LOCAL)

$(LIB_LOCAL): $(OBJ)
	$(CC) $(LDFLAGS) -o $@ $^
	@echo "built $@"

src/%.o: src/%.c include/monolog_parser.h
	$(CC) $(CFLAGS) -DMONOLOG_PARSER_BUILD -c -o $@ $<

tests/test_parser: tests/test_parser.c $(LIB_LOCAL)
	$(CC) $(CFLAGS) -o $@ tests/test_parser.c -L. -lmonolog_parser $(TEST_RPATH)

bench/bench: bench/bench.c $(LIB_LOCAL)
	$(CC) $(CFLAGS) -o $@ bench/bench.c -L. -lmonolog_parser $(TEST_RPATH)

test: tests/test_parser
	LD_LIBRARY_PATH="$(CURDIR):$$LD_LIBRARY_PATH" ./tests/test_parser

bench: bench/bench
	LD_LIBRARY_PATH="$(CURDIR):$$LD_LIBRARY_PATH" ./bench/bench 100000

package: $(LIB_LOCAL)
	mkdir -p $(INSTALL_DIR)
	cp -f $(LIB_LOCAL) $(INSTALL_DIR)/$(LIB_PACKAGED)
	cp -f include/monolog_parser.h $(INSTALL_DIR)/
	@echo "packaged $(INSTALL_DIR)/$(LIB_PACKAGED)  [libc=$(LIBC)]"

install: package
	cp -f $(LIB_LOCAL) $(INSTALL_DIR)/$(LIB_LOCAL)

# ---- Zig cross-compile (all platforms from one machine) ------------------

zig-release:
	ZIG="$(ZIG)" OUT="$(abspath $(INSTALL_DIR))" sh ./build-zig.sh

# make zig-one TARGET=linux-musl-arm64
zig-one:
	@test -n "$(TARGET)" || { echo "usage: make zig-one TARGET=linux-musl-arm64"; exit 1; }
	ZIG="$(ZIG)" OUT="$(abspath $(INSTALL_DIR))" sh ./build-zig.sh $(TARGET)

clean:
	rm -f $(OBJ) $(LIB_LOCAL) tests/test_parser bench/bench
