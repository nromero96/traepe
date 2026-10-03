FROM golang:1.24.8-alpine3.22@sha256:3d78beb141d98f42337f1252ecf2a5f20374109929a4c3f6817f9e4179cc0ae5 AS builder
ENV CGO_ENABLED=0 GOFLAGS=-p=2 GOTOOLCHAIN=local
# Official MinIO RELEASE.2025-10-15T17-29-55Z and mc RELEASE.2025-08-13T08-35-41Z.
RUN go install github.com/minio/minio@9e49d5e7a648f00e26f2246f4dc28e6b07f8c84a \
    && go install github.com/minio/mc@7394ce0dd2a80935aded936b09fa12cbb3cb8096

FROM alpine:3.22.2@sha256:4b7ce07002c69e8f3d704a9c5d6fd3053be500b7f1c69fc0d80990c2ad8dd412
RUN apk add --no-cache ca-certificates
COPY --from=builder /go/bin/minio /usr/local/bin/minio
COPY --from=builder /go/bin/mc /usr/local/bin/mc
ENTRYPOINT ["minio"]
CMD ["server", "/data", "--console-address", ":9001"]
