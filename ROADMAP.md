# WP Users Export — Roadmap

Este documento descreve melhorias planejadas referentes à segurança e novos recursos.

## Segurança
- Validação e privilégios (hardening)
  - Garantir uso consistente de `current_user_can('list_users')` (feito) e revisar todas as ações.
  - Nonces em todos os formulários (feito) e verificação robusta.
  - Sanitização/escape de todos os inputs (datas, regex) e saídas em telas.
- Privacidade e compliance
  - Opção para anonimizar/mascarar dados sensíveis (ex.: truncar e-mail) quando necessário.
  - Checkbox de confirmação para LGPD/GDPR antes da exportação.
  - Registro (log) de quem exportou, quando e com quais filtros (apenas para admins).
- Performance e disponibilidade
  - Mover exportações grandes para jobs assíncronos (cron/Action Scheduler) para evitar timeouts.
  - Limitar taxa (rate limiting) e impor timeouts configuráveis.
- Integridade
  - Assinar o arquivo exportado (checksum SHA-256) e disponibilizar junto no ZIP.
  - Guardar o histórico de carimbos de "última exportação" por escopo (por função, por regex opcional).

## Novos recursos
- Filtros e seleção de campos
  - Filtrar por função (role) e por status (ex.: aprovados, se houver plugins que adicionem esse meta).
  - Seleção de campos/metadados customizados para o CSV.
  - Inclusão de intervalo por `user_registered` com timezone configurável.
- Formatos e entrega
  - Suporte a XLSX e JSON além de CSV/TXT.
  - Opção de exportar diretamente para um provedor de armazenamento (S3, GCS) via credenciais do admin.
  - Envio do link por e-mail para o solicitante quando a exportação assíncrona concluir.
- UX e visibilidade
  - Mostrar data/hora da última exportação por tipo na tela.
  - Barra de progresso para exportações grandes.
  - Histórico de exportações e possibilidade de re-download.
- API e automação
  - Endpoint REST seguro para disparar exportações (apenas para usuários com permissão), com tokens/keys.
  - Hooks/filters para desenvolvedores alterarem colunas, filtros e formato.
- Multisite
  - Suporte a exportação por site individual e opcionalmente "network-wide" (agregado com coluna de origem).
- Internacionalização e qualidade
  - Catálogo de traduções (`.pot`) e traduções adicionais.
  - Testes automatizados (PHPUnit) e testes de integração com WordPress.

## Marcos sugeridos
- 1.1.0 — Filtros por função, exibição de última exportação, seleção de campos básicos, `.pot` de tradução.
- 1.2.0 — Exportação assíncrona com Action Scheduler, histórico básico, notificação por e-mail.
- 2.0.0 — Formatos XLSX/JSON, endpoint REST, assinaturas de integridade e logs de auditoria.
