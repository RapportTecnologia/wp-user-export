# Changelog

## [1.0.51] - 2025-08-17
### Changed
- Cabeçalho do plugin: autor com email, `Requires at least`, `Requires PHP`, `Tested up to`.
- README.md atualizado com: instalação, suporte via GitHub Issues, sugestões e convite de contribuição (PIX), e badge de visitantes.

### Added
- Rodapé na página admin do plugin com links para o repositório e site (`rapport.tec.br`).

## [1.0.50] - 2025-08-17
### Added
- Ações na lista de plugins: link de Configurações e alternância para ativar/desativar atualização automática.
- Handler `admin_post_wpue_toggle_auto_update` para alternar auto-update a partir da lista de plugins.
- UI com abas em `Usuários → WP Users Export`: “Exportação” e “Configuração”.

### Improved
- Melhor integração do fluxo de atualização via listagem de plugins (update checker já integrava o processo).

## [1.0.42] - 2025-08-17
### Changed
- Período de cache para verificação de novo release no GitHub alterado de 6 horas para 1 semana.
- Mantida a verificação manual via botão “Verificar agora” na aba Configuração.

## [1.0.40] - 2025-08-17
### Added
- Verificação de novo release no GitHub (API) com cache em transient.
- Integração com o sistema de updates do WordPress (oferece pacote .zip do release).
- Aviso no admin quando houver nova versão disponível.
- Opção para habilitar atualização automática do plugin (checkbox em Configurações da página de exportação).
- Secção “Configurações” na UI para salvar a preferência de auto-update.

### Changed
- Melhoria geral de UX na página de exportação ao incluir área de configurações.

### Notes
- O pacote para atualização é obtido diretamente do release (tag) no GitHub.
- A consulta de novas versões é feita a cada 6 horas (transient).

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
