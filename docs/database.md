# Base de datos

La persistencia usa migraciones incrementales y modelos Eloquent. Producción está orientada a MySQL/MariaDB; las pruebas pueden usar SQLite. Nunca debe ejecutarse `migrate:fresh` sobre una base funcional.

## Grupos de tablas

- Identidad y plataforma: `users`, `roles`, `areas`, `password_reset_tokens`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- Catálogo: `procedure_categories`, `procedure_types`, `presentation_modalities`, `procedure_variants`, `procedure_requirements`, `procedure_dynamic_fields`, `procedure_prerequisites`.
- Solicitudes: `procedure_requests`, `request_documents`, `request_histories`, `request_observations`, `request_derivations`, `request_responses`, `request_rejections`, `request_corrections`, `request_attention_actions`, `request_physical_receptions`.
- Comunicación: `notifications`, `request_appointments`, `request_pickups`.
- Titulación: `title_processes`, `title_stage_events`, `title_schedules`, `title_process_documents`, tablas `application_work_*`, `professional_exam_*` y `title_final_dossier*`.
- Control: `audit_logs`, `tracking_sequences`, `institutional_catalog_versions`, `institutional_catalog_sync_records`, `backup_logs`.

Las migraciones posteriores amplían tablas sin eliminar columnas históricas. Los snapshots preservan la interpretación de solicitudes aun cuando cambie el catálogo. Los documentos se relacionan mediante metadatos y rutas privadas; no se almacenan como enlaces públicos.

## Seeders

`DatabaseSeeder` ejecuta catálogos de roles, áreas, estados, tipos de trámite y usuarios ficticios. `InstitutionalCatalogSeeder` sincroniza el catálogo institucional solo en local o testing. Antes de producción deben revisarse cuentas y datos de demostración.
