# WP Users Export

[![Release](https://img.shields.io/github/v/release/RapportTecnologia/wp-user-export?display_name=tag&sort=semver)](https://github.com/RapportTecnologia/wp-user-export/releases)

Exporta usuários do WordPress de forma simples. Disponibiliza dois botões na área administrativa em "Usuários → Exportar Usuários":

- Exportar usuários (CSV) — inclui dados e estatísticas.
- Exportar e-mails (TXT) — apenas e-mails, um por linha.

As exportações são entregues compactadas em arquivos ZIP.

## Recursos
- Exportação de usuários em CSV com: ID, user_login, first_name, last_name, display_name, user_email, roles, user_registered, posts_count, comments_count, user_url.
- Exportação de e-mails (um por linha) em TXT.
- Filtros na interface:
  - Apenas novos desde a última exportação (por tipo de exportação).
  - Intervalo de datas de registro (início/fim).
  - Regex (case-insensitive) aplicado a email, login e nome de exibição.
- Processamento em lotes para lidar com bases grandes.
- Geração de arquivo temporário (CSV/TXT) e compactação com ZipArchive.

## Requisitos
- WordPress: Requires at least 5.8 (Tested up to 6.5)
- PHP: >= 7.4
- Extensão PHP Zip (ZipArchive) habilitada

## Instalação
1. Baixe o pacote `wp-users-export.zip`.
2. No painel do WordPress, vá em `Plugins → Adicionar novo → Enviar plugin` e envie o ZIP.
3. Instale e ative o plugin.

## Uso
- Acesse `Usuários → Exportar Usuários`.
- Opcionalmente defina os filtros:
  - Marque "Exportar apenas novos desde a última exportação" para exportar incrementalmente.
  - Defina datas de início/fim para limitar pelo `user_registered`.
  - Informe um regex para filtrar por email/login/display_name (ex.: `^.*@dominio\.com$`).
- Clique em um dos botões de exportação. O download será um `.zip` contendo o arquivo CSV ou TXT correspondente.

## Notas
- Ao usar "apenas novos", o plugin mantém um carimbo de tempo separado para cada tipo de exportação e o atualiza ao término de cada exportação.
- Em ambientes com muitos usuários, a exportação ocorre em lotes.

## Suporte e Autor
- Autor: Carlos Delfino
- E-mail: consultoria@carlosdelfino.eti.br
- GitHub: https://github.com/carlosdelfino

## Licença
Consulte o arquivo `LICENSE` no repositório do projeto.
