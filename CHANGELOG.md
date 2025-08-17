# Changelog

## [1.0.21] - 2025-08-17
### Added
- Tela de administração em `Usuários → Exportar Usuários` com dois botões de exportação.
- Exportação de usuários (CSV) com colunas: ID, user_login, first_name, last_name, display_name, user_email, roles, user_registered, posts_count, comments_count, user_url.
- Exportação de e-mails (TXT) com um e-mail por linha.
- Filtros de exportação:
  - Apenas novos desde a última exportação (mantém carimbo por tipo de exportação).
  - Intervalo de datas por `user_registered` (início/fim).
  - Regex (case-insensitive) aplicado a email/login/display_name.
- Saída compactada em `.zip` (ZipArchive) para CSV e TXT.
- README.md com instruções e ROADMAP.md com plano de evolução.

### Changed
- Processamento em lotes para melhorar desempenho em bases grandes.
- CSV com BOM UTF-8 para compatibilidade com Excel/Windows.

### Security/Reqs
- Cabeçalho do plugin com `Requires at least`, `Tested up to` e `Requires PHP`.
- Verificação na ativação: versões mínimas de WP/PHP e presença da extensão ZipArchive.

[1.0.21]: https://github.com/RapportTecnologia/wp-user-export/releases/tag/v1.0.21
