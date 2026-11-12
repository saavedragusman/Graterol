# AGENTS.md — ERP Graterol

> Instrucciones operativas para agentes (gentle-shell + gentle-ai + Engram).
> Responde siempre en español. Código, identificadores, tablas y permisos en inglés; textos de pantalla en español. documentacion dentro del codigo en español

## 0. Regla fundamental
**No inventar el negocio.** Si un comportamiento no está definido, se detiene esa parte y se pregunta (§5). Lo puramente técnico se resuelve de forma simple y coherente con la constitution.

## 1. Qué es
ERP instalable y configurable para distribuidoras de cárnicos (compras, despiece, inventario, POS, reportes y facturación). Distribuidora Graterol (Valera) es el piloto. Una instalación y una base de datos por cliente; lo que varía es configuración, no código.

## 2. Stack y comandos
PHP 8.4 · Laravel 13 · PostgreSQL · Filament 5 · Inertia + Vue 3 + TypeScript + Tailwind (PWA) · Pest · Larastan · Pint.
Para Laravel, Filament, Inertia, Vue o Pest se consulta Context7 con la versión instalada; no asumir APIs de memoria.

Todo corre en Sail dentro de WSL. Los agentes usan `./vendor/bin/sail`, nunca `php` ni `composer` del sistema. JS solo con pnpm.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan test                 # o: sail pest --filter=BR-12
./vendor/bin/sail composer types:check         # Larastan nivel 6
./vendor/bin/sail pint
./vendor/bin/sail pnpm build
./vendor/bin/sail artisan migrate:fresh --seed # SOLO en local
```
Las pruebas usan la base `testing` de PostgreSQL, nunca SQLite: el trigger del kardex solo existe en PostgreSQL.
La carga de la tasa BCV (`bcv:fetch`) llama a la API real; solo se ejecuta si el humano lo pide.

## 3. Jerarquía de documentos
| Nivel | Archivo | Manda sobre |
|---|---|---|
| 1 | `docs/constitution.md` | Principios C1–C24 |
| 2 | `docs/business/business-rules.md` | Reglas de dominio BR-xx |
| 3 | `docs/roadmap.md` | Hitos, alcance y criterio de cierre |
| 4 | `odd/tasks/<hito>-<feature>.md` | Diseño, tareas y evidencia de un hito |
| 5 | Código y pruebas | Implementación |

Un nivel inferior nunca contradice a uno superior; si lo hace, se detiene y se reporta. Si Engram y el repo difieren, manda el repo.

**Referencia de interfaz:** `docs/design.md` (tokens, componentes y patrones) y `docs/design/screens/*.html` (pantallas estáticas con datos de ejemplo). Mandan solo sobre estilos, layout y componentes, nunca sobre los niveles 1–4. Lo que aparece dibujado sin BR ni hito no se implementa: se registra como `[DEC-PENDIENTE]`.

## 4. Flujo ODD
### 4.1 Clasificación
| Cambio | Ruta |
|---|---|
| Hito con documento en el roadmap (M0, M2–M5, M8) | Documento de feature + puertas |
| Hito directo (M1, M6, M7), refactor, estilos, typos | Directo, con pruebas en verde |
| Bug que contradice una BR | Directo, con el test de la BR que fallaba |

### 4.2 Documento de feature: `odd/tasks/<hito>-<feature>.md` (ej. `m3-butchery.md`)
1. **Fuente**: hito del roadmap y pedido literal del humano.
2. **Alcance**: IDs BR-xx cubiertos. Nada fuera de ellos.
3. **Decisiones**: `[DEC-PENDIENTE]` y su resolución debajo («Resuelta: A, 2026-11-20»), o «Sin decisiones pendientes».
4. **Diseño técnico**: tablas, migraciones, Actions, Policies, permisos, rutas y pantallas. Cada pantalla cita su referencia en `docs/design/screens/` y los componentes de `docs/design.md` §3 que usa.
5. **Tareas**: checklist; cada tarea cita sus BR y sus dependencias (`depende de 3.1`).
6. **Evidencia**: test por cada BR, resultado de los comandos de §2, hashes de los commits y siguiente paso.

Copia en Engram: `odd/<hito>-<feature>/tasks`.

### 4.3 Puertas
1. **Antes de la primera línea de código:** el documento escrito y aprobado por el humano, **una sola vez por hito**. Después solo se vuelve a parar ante un `[DEC-PENDIENTE]` o un cambio al Diseño técnico.
2. **Cierre del hito:** las BR del alcance en verde, la Evidencia registrada y la confirmación del humano. En riesgo alto (C18): `gentle-ai review assess` sobre el rango de commits del hito; si sale `high`, se propone la revisión y se espera.

### 4.4 Git
- Una rama por hito: `feat/m3-butchery`. Sueltos: `fix/…`, `chore/…`.
- Un commit por tramo revisable, con pruebas en verde: `feat(M3): costeo por valor relativo [BR-12]`.
- El agente nunca hace push o merge ni reescribe historia (`rebase`, `amend`, `reset`) sin que se le pida.

## 5. Cómo preguntar
```text
[DEC-PENDIENTE] <hito>/<BR afectada>
Contexto: qué se estaba implementando.
Duda: qué no está definido.
Opciones: A) … B) … (con consecuencias)
Recomendación técnica: …
```
La pregunta va en Decisiones del documento ODD y se avisa al humano; mientras tanto se sigue con las tareas independientes. Las reglas marcadas `[abierto]` en business-rules usan su valor por defecto configurable y no bloquean. Una respuesta que contradiga la constitution es una decisión arquitectónica: se cambia primero la constitution.

## 6. Convenciones de código
- Controlador o recurso Filament: valida (FormRequest), autoriza (Policy) y delega en `app/Domain/<Module>/Actions`, donde viven la regla y la transacción.
- Permisos `module.action` declarados en un solo enum o seeder.
- Vue: páginas en `resources/js/pages/<module>/`, componentes en `resources/js/components/`.
- Interfaz: antes de crear o cambiar una pantalla se lee `docs/design.md`. Colores, fuentes y radios solo por los tokens de `docs/design.md` §2; no se copian estilos en línea ni hex de las pantallas de referencia.
- Las migraciones aplicadas en un entorno compartido no se editan: se crea una nueva.
- Seeds con datos ficticios; nunca precios, costos ni clientes reales.

## 7. Engram
**Se guarda:** decisiones técnicas y de dominio, hallazgos no obvios, errores recurrentes y su solución.
**No se guarda:** credenciales, `.env`, datos personales, ni precios o costos reales del cliente.

## 8. Definition of Done
- [ ] Las BR del alcance tienen test en verde.
- [ ] Respeta la constitution: Policies, transacciones, auditoría donde aplica (C15), sin float.
- [ ] Las pantallas nuevas usan los tokens y componentes de `docs/design.md`.
- [ ] Pasan `artisan test`, `pint --test`, `composer types:check`, `pnpm build` y GGA.
- [ ] El documento ODD está completo, con su Evidencia.
- [ ] El humano confirmó el cierre.

## 9. Modo autónomo
Solo se activa cuando el humano lo indica, y para los hitos o tareas que nombre. Ante la duda, se elige lo más conservador y reversible.
- **Bloqueos:** un `[DEC-PENDIENTE]` bloquea su tarea y las que dependen de ella. Nada de stubs ni valores provisionales que supongan la respuesta. Se sigue con lo independiente; un tramo con tareas bloqueadas no se commitea como cerrado.
- **Git:** commits sí. Prohibido push, PR, merge, rebase, cherry-pick, amend, `reset`, `clean -fd`, `branch -D`, `stash drop` y cualquier `--force`.
- **Base de datos:** las pruebas corren solo contra `testing`; si `phpunit.xml` no apunta a ella, se detiene. No se ejecutan `migrate*`, `db:seed` ni `db:wipe` sobre la base de desarrollo. Prohibido DROP, TRUNCATE y DELETE o UPDATE sin WHERE.
- **Dependencias e infraestructura:** prohibido `composer require/update/remove`, `pnpm add/update/remove` y editar lockfiles, `.env`, `compose.yaml` o Dockerfiles. Prohibido `down -v` y `docker volume rm`. Si falta una dependencia, se anota.
- **Documentos:** no se editan constitution, business-rules, roadmap, AGENTS, `docs/design.md` ni `docs/design/screens/`. Sí el documento ODD.
- **Pruebas:** no se borran, saltan ni debilitan. Si un error persiste tras 3 intentos, se documenta y se pasa a otra tarea.
- **Efectos externos:** `MAIL_MAILER=log`; no se llama a la API del BCV ni a la del SENIAT o imprenta, ni se imprime.
- **Al terminar:** `report/REPORTE-SESION-<n>.md` con commits, decisiones técnicas, bloqueos, dependencias faltantes, `[DEC-PENDIENTE]` y candidatos a review `high`.
