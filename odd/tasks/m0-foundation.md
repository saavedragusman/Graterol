# M0 — Fundación

Hito del roadmap (`docs/roadmap.md`): repo, Sail, PostgreSQL, Filament, Pest, Larastan, GGA, glosario, modelo de datos inicial y kardex. 12 h. ODD: feature + verificador (C18, por el riesgo de la migración con trigger sobre datos).

## 1. Fuente

- `docs/roadmap.md`, fila M0: *"El trigger del kardex rechaza `UPDATE`/`DELETE` (test); los tests de arquitectura de C9 y C14 pasan; un commit pasa GGA."*
- Pedido literal del humano: glosario de dominio, modelo de datos inicial de los módulos que M0 necesita, tabla `inventory_movements` con su trigger, tests de arquitectura C9 y C14, tareas con dependencias y criterio de cierre. Sin código todavía.
- Exploración del repo (subagente `gentle-ai-explore`, tarea `mwbb9gwn-1-tbta`): no existe `app/Domain/`, ni migraciones de dominio, ni `tests/Architecture/`, ni Pest instalado. `composer.json` pide PHP `^8.3` (la constitution exige 8.4, C1). `.env.example` y `config/database.php` usan `sqlite` por defecto (C1 exige PostgreSQL). No hay `spatie/laravel-permission` ni ningún paquete de roles/permisos.

## 2. Alcance

Cubre únicamente lo que el roadmap pide en M0:

- **C1** — corregir el entorno para que cumpla el stack declarado (PHP 8.4, PostgreSQL por defecto, Pest instalado).
- **C9** — tipos de dato para peso y dinero; test de arquitectura que vigila que no se usen `float`/`double`.
- **C10** — tabla `inventory_movements` y su trigger de inmutabilidad.
- **C14** — infraestructura mínima de permisos `module.action` (tablas), sin sembrar roles todavía, y test de arquitectura que vigila `hasRole()`, `Gate::before` y `abort(403)`.
- Glosario de dominio (pedido explícito del roadmap para M0).

**Fuera de alcance de M0** (quedan para su propio hito, no se inventan aquí):

- Sembrar los roles `administrador`, `cajero`, `carnicero` y sus permisos — eso es **BR-03**, en **M1**.
- Catálogo de productos, compras, despiece, ventas, canales de venta, tasa BCV — **M1 a M5**.
- Cualquier Policy real (no hay recurso que proteger todavía).

## 3. Decisiones

Sin decisiones de negocio pendientes: M0 es fundación técnica, no hay comportamiento de dominio que inventar.

Una decisión puramente técnica, resuelta de forma simple y coherente con la constitution (C19, sin bloquear):

> Los permisos y roles se implementan con tablas propias (`roles`, `permissions`, `role_permission`, `role_user`) en vez de un paquete externo (p. ej. `spatie/laravel-permission`). Motivo: evita una dependencia nueva que requeriría aprobación (C1) para resolver algo simple, y es coherente con C6 (configuración en datos simples, sin motores de reglas de terceros). Si en M1 esto no alcanza para BR-03, se reevalúa.

Una nota que sí necesita confirmación del humano antes de ejecutar tareas (no es `[DEC-PENDIENTE]` de negocio, es la aprobación de dependencia que exige C1):

> T2 instala Pest (`pestphp/pest` + `pestphp/pest-plugin-laravel`, y posiblemente `pestphp/pest-plugin-arch` para los tests de arquitectura). Es una dependencia nueva: según C1, la aprueba el humano y queda registrada en Engram antes de correr `composer require`.

## 4. Diseño técnico

### 4.1 Glosario de dominio

| Término | Definición en este negocio |
|---|---|
| **Canal** | Vía de venta (mostrador, mayorista, etc.). Define listas de precio y reglas de pago aplicables (BR-18, BR-19). Tabla propia en M5; aquí solo se define el concepto porque el roadmap lo pide en el glosario. |
| **Lote** | Unidad de inventario físico con un costo propio. Nace en una recepción de compra (BR-07) o como salida de una orden de despiece (BR-12). Se consume por FEFO (BR-14). Tabla `batches`, esqueleto en M0, ampliada en M2/M3. |
| **Corte** | Producto derivado de un despiece (ej. pulpa negra, chuleta). Definido por una plantilla de despiece y con su propio valor relativo de mercado para el costeo (BR-12). Tabla propia en M3. |
| **Plantilla** | Conjunto de cortes esperados de un tipo de despiece, con el porcentaje de rendimiento que cada corte debería dar sobre el peso de entrada; debe sumar 100 % (BR-08). Tabla propia en M3. |
| **Merma** | Pérdida de peso o unidades tipificada: deshidratación, rebanado, vencimiento, decomiso o ajuste por conteo (BR-15). Se registra siempre con motivo y responsable, como un tipo de movimiento de inventario. |
| **Rendimiento** | Relación entre el peso real obtenido de cada corte y el peso esperado según la plantilla; se compara para alertar desvíos (BR-11) y para el reporte de margen por corte (BR-28). |
| **Orden de despiece** | Documento que abre un lote de entrada, aplica una plantilla y registra las salidas (cortes) y la merma real; la cierra un carnicero (BR-09, BR-10). Tabla propia en M3; cerrada, no se edita (C12). |
| **Movimiento de inventario** | Cada entrada o salida de un lote: compra, despiece (entrada/salida), venta, merma o ajuste por conteo. Es la única fuente de verdad del stock (BR-14); se guarda, nunca se corrige, solo se revierte (C10, C12). Tabla `inventory_movements`, construida completa en M0 porque es el criterio de cierre del hito. |
| **Tasa** | Tasa de cambio BCV del día, con respaldo manual si falla la carga automática (BR-02). Cada operación que la usa guarda el valor que aplicó (C13). Tabla propia en M1. |

### 4.2 Modelo de datos inicial

Solo las tablas que M0 necesita para cumplir su criterio de cierre: infraestructura de permisos (C14) y kardex (C10), más un esqueleto mínimo de `batches` para que `inventory_movements` tenga una referencia real. El resto de las tablas de dominio se diseñan en su propio hito, para no inventar columnas que esos hitos todavía no han definido (C19).

**`permissions`**
| Columna | Tipo | Nota |
|---|---|---|
| `id` | `bigint` PK | |
| `name` | `varchar(100)` único | Formato `module.action` (C14), ej. `inventory.adjust`. |
| `created_at`, `updated_at` | `timestamp` | |

**`roles`**
| Columna | Tipo | Nota |
|---|---|---|
| `id` | `bigint` PK | |
| `name` | `varchar(100)` único | Ej. `administrador`, `cajero`, `carnicero` — sembrados en M1 (BR-03), no en M0. |
| `created_at`, `updated_at` | `timestamp` | |

**`role_permission`** (pivote)
| Columna | Tipo | Nota |
|---|---|---|
| `role_id` | `bigint` FK → `roles.id` | PK compuesta con `permission_id`. |
| `permission_id` | `bigint` FK → `permissions.id` | |

**`role_user`** (pivote)
| Columna | Tipo | Nota |
|---|---|---|
| `user_id` | `bigint` FK → `users.id` | PK compuesta con `role_id`. |
| `role_id` | `bigint` FK → `roles.id` | |

**`batches`** (lotes — esqueleto; se amplía en M2 con BR-07 y en M3 con BR-12)
| Columna | Tipo | Nota |
|---|---|---|
| `id` | `bigint` PK | |
| `code` | `varchar(30)` único | Código visible del lote, ej. `L-0412`. |
| `source_type` | `varchar(30)` | `purchase` o `butchery`; de dónde nace el lote. Se define la referencia real (FK polimórfica a la compra o a la orden de despiece) en M2/M3. |
| `source_id` | `bigint` nullable | Id del origen; sin FK formal todavía porque esas tablas no existen en M0. |
| `unit` | `varchar(10)` | `kg` o `unidad`. |
| `unit_cost` | `numeric(18,4)` | Costo unitario del lote (C9, dinero). |
| `received_at` | `timestamp` | Cuándo entró el lote. |
| `expires_at` | `timestamp` nullable | Usado por FEFO (BR-14). |
| `created_at`, `updated_at` | `timestamp` | |

**`inventory_movements`** (kardex — C10, criterio de cierre del hito)
| Columna | Tipo | Nota |
|---|---|---|
| `id` | `bigint` PK | |
| `batch_id` | `bigint` FK → `batches.id` | Lote (C10: "cada movimiento guarda lote"). |
| `type` | `varchar(30)` | Tipo de movimiento (C10): `purchase_in`, `butchery_in`, `butchery_out`, `sale_out`, `shrinkage`, `count_adjustment`, `reversal`. |
| `quantity` | `numeric(12,3)` | Peso o cantidad (C9: peso `numeric(12,3)`, sin float). Entradas positivas, salidas negativas. |
| `unit_cost` | `numeric(18,4)` | Costo unitario del movimiento (C9: dinero `numeric(18,4)`, sin float). |
| `user_id` | `bigint` FK → `users.id` | Usuario que lo generó (C10). |
| `reference_type` | `varchar(60)` nullable | Documento de origen (compra, orden de despiece, venta, ajuste); polimórfica simple. |
| `reference_id` | `bigint` nullable | Id de ese documento. |
| `reason` | `varchar(255)` nullable | Motivo (C10); obligatorio a nivel de Action para `shrinkage` y `count_adjustment` (BR-15, BR-16), no a nivel de columna porque no aplica a todos los tipos. |
| `occurred_at` | `timestamp` | Cuándo ocurrió el movimiento (puede diferir de cuándo se registró). |
| `created_at` | `timestamp` | Sin `updated_at`: la tabla es de solo inserción (C10). |

**Trigger de inmutabilidad (C10):**

```sql
CREATE FUNCTION inventory_movements_block_update_delete()
RETURNS trigger AS $$
BEGIN
  RAISE EXCEPTION 'inventory_movements es de solo inserción: use un movimiento inverso';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_inventory_movements_immutable
BEFORE UPDATE OR DELETE ON inventory_movements
FOR EACH ROW EXECUTE FUNCTION inventory_movements_block_update_delete();
```

La migración crea la función y el trigger en `up()` con `DB::statement(...)`, y los elimina en `down()` (`DROP TRIGGER`, `DROP FUNCTION`), por ser PostgreSQL puro (no hay equivalente portable y el proyecto no corre sobre SQLite, AGENTS §2).

### 4.3 Tests de arquitectura

**C9 — sin `float`/`double` en peso o dinero.**
Test en `tests/Architecture/C9NoFloatTest.php` que recorre `database/migrations/*.php` y `app/Domain/**/*.php` buscando los tokens `float` y `double` (palabra completa, insensible a mayúsculas) fuera de comentarios, y falla si aparece alguno. Cubre tanto columnas de migración (`$table->float(...)`) como casts y cálculos en Actions.

**C14 — sin `hasRole()`, `Gate::before`, `abort(403)`.**
Test en `tests/Architecture/C14NoRoleHelpersTest.php` que recorre `app/**/*.php` (excluyendo `vendor`) buscando literalmente `hasRole(`, `Gate::before(` y `abort(403)`, y falla si aparece alguno. Vigila que la autorización pase siempre por Policies y permisos `module.action`.

Ambos se escriben como pruebas Pest planas (lectura de archivos + aserciones), no como `arch()->expect(...)` de Pest, porque lo que se vigila es texto fuente, no la forma de las clases.

## 5. Tareas

- [x] **T1 — Corregir el entorno base para que cumpla C1.** Sin dependencias.
  - `composer.json`: `"php": "^8.4"`.
  - `config/database.php`: conexión por defecto `pgsql`.
  - `.env.example`: `DB_CONNECTION=pgsql` con el resto de variables de PostgreSQL.
  - Cierre: `sail artisan migrate` conecta contra PostgreSQL sin tocar `.env` de nadie más.

- [x] **T2 — Instalar Pest.** Depende de T1. **Requiere aprobación previa del humano (C1, dependencia nueva).** Aprobado y ejecutado por el humano.
  - `pestphp/pest`, `pestphp/pest-plugin-laravel`; configurar `tests/Pest.php`; migrar `tests/Feature/ExampleTest.php` y `tests/Unit/ExampleTest.php` o retirarlos.
  - Cierre: `sail artisan test` corre con Pest.

- [x] **T3 — Confirmar Larastan nivel 6 y Pint.** Depende de T1.
  - Revisar `phpstan.neon` (nivel 6) y la config de Pint.
  - Cierre: `sail composer types:check` sin errores; `sail pint --test` sin cambios pendientes.

- [x] **T4 — Glosario de dominio.** Sin dependencias; ya redactado en §4.1.
  - Cierre: el humano lo aprueba junto con este documento.

- [x] **T5 — Migración de `roles`, `permissions`, `role_permission`, `role_user`.** Depende de T1.
  - Infraestructura de permisos `module.action` (C14). No siembra datos: eso es BR-03 en M1.
  - Cierre: migración corre en la base `testing`.

- [x] **T6 — Trait `HasPermissions` en `User`.** Depende de T5.
  - Método `hasPermission(string $permission): bool` para que las Policies lo usen; es el único camino de autorización permitido por C14.
  - Cierre: el código compila; sin test de negocio todavía porque no hay Policy real en M0.

- [x] **T7 — Migración de `batches` (esqueleto).** Depende de T1.
  - Columnas mínimas de §4.2; se amplía en M2 (BR-07) y M3 (BR-12).
  - Cierre: migración corre en `testing`.

- [x] **T8 — Migración de `inventory_movements` + trigger de inmutabilidad.** Depende de T7 (FK `batch_id`).
  - Tabla y trigger de §4.2 (C10).
  - Cierre: migración corre en `testing` sobre PostgreSQL.

- [x] **T9 — Test: el trigger rechaza `UPDATE` y `DELETE` sobre `inventory_movements`.** Depende de T2, T8. Cita C10.
  - Cierre: test en verde. **Criterio de cierre del roadmap para M0.**

- [x] **T10 — Test de arquitectura C9 (sin `float`/`double`).** Depende de T2.
  - Cierre: test en verde. **Criterio de cierre del roadmap para M0.**

- [x] **T11 — Test de arquitectura C14 (sin `hasRole()`, `Gate::before`, `abort(403)`).** Depende de T2, T6.
  - Cierre: test en verde. **Criterio de cierre del roadmap para M0.**

- [ ] **T12 — Commit por tramo revisable y GGA.** Depende de T1–T11.
  - Rama `feat/m0-foundation`; un commit por tramo con pruebas en verde (AGENTS §4.4); el último tramo pasa GGA.
  - Cierre: GGA aprobado. **Criterio de cierre del roadmap para M0.**

- [ ] **T13 — Verificador independiente (C18).** Depende de T9, T12.
  - El roadmap marca M0 como "feature + verificador" por el riesgo de tocar migraciones y un trigger de base de datos (C18, riesgo alto: migraciones con datos). El verificador revisa en particular T8/T9.
  - Cierre: verificador independiente confirma antes de cerrar el hito.

## 6. Evidencia

| Regla | Test | Resultado | Commit |
|---|---|---|---|
| C10 (trigger kardex) | `tests/Feature/Inventory/InventoryMovementsImmutableTest.php` (rejects UPDATE/DELETE) | verde (2/2) | T5-T9 |
| C9 (sin float/double) | `tests/Architecture/C9NoFloatTest.php` | verde (2/2) | T5-T9 |
| C14 (sin hasRole/Gate::before/abort(403)) | `tests/Architecture/C14NoRoleHelpersTest.php` | verde (1/1) | T5-T9 |

Comandos de §2 de AGENTS.md ejecutados: `sail pest` (5/5 verde, dos corridas para confirmar idempotencia de la migración del trigger), `sail composer types:check` (0 errores, Larastan nivel 6), `sail pint --test` (sin cambios pendientes), `sail pnpm build` (compila).

Nota técnica: la migración del trigger usa `CREATE OR REPLACE FUNCTION` y `DROP TRIGGER IF EXISTS` antes de crear, porque `migrate:fresh` (usado por `RefreshDatabase` en los tests) no elimina funciones de PostgreSQL entre corridas; sin eso, la segunda corrida de tests fallaba con "Duplicate function".

Pendiente: T12 (commit del tramo + GGA) y T13 (verificador independiente, C18).

Siguiente paso: commit del tramo T1-T11 y, dado el riesgo alto de tocar migraciones y un trigger de base de datos (C18), proponer el verificador independiente (T13) antes de cerrar el hito.
