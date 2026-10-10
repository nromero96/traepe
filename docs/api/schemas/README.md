# Esquema de validación OpenAPI

`openapi-3.0-2024-10-18.json` es una copia sin modificaciones del [esquema oficial OAS 3.0](https://spec.openapis.org/oas/3.0/schema/2024-10-18), descargada el 3 de octubre de 2026.

SHA-256: `2385f5bbb8c37878daae73baeabe7f34b2f022a4a8c049329ee61f71796f039c`.

Origen: OpenAPI Initiative / Linux Foundation, repositorio [OAI/OpenAPI-Specification](https://github.com/OAI/OpenAPI-Specification), licencia Apache 2.0 conservada en [LICENSE](LICENSE). Es un fixture de documentación, no una dependencia de ejecución.

El contrato usa [OpenAPI 3.0.3](https://spec.openapis.org/oas/v3.0.3.html), suficiente para las rutas técnicas y compatible con el validador Draft-04 incluido en el Composer fijado. El lint no necesita red ni instala paquetes. Las pruebas de contrato complementan el esquema con referencias locales resolubles, correspondencia de rutas y forma de respuestas reales.

`platform-technical-probe.v1.json` es el contrato propio de `platform.technical_probe.v1`, incorporado en 00E. No pertenece al esquema descargado de OAI. El lint comprueba el contenido del evento técnico persistido y cifrado, sin imprimirlo, y rechaza un evento sin versión. Domain valida adicionalmente la identidad compartida entre agregado/payload y la inmutabilidad en memoria.

[marketplace-local-draft-fixture-operation.v1.json](marketplace-local-draft-fixture-operation.v1.json) es el contrato propio Draft-04 del snapshot privado de creación local aprobado en DP-031/02F. Conserva forma cerrada, perfil A/B y referencias ULID, con tres zonas distintas en orden A/B/C. El lint existente valida un ejemplo y rechaza propiedades extras, perfil/referencia inválidos y zonas repetidas usando el validador ya incluido en Composer. OpenAPI 1.5.0 incorpora la misma forma; Domain la valida antes de persistir y al restaurar el resultado.
