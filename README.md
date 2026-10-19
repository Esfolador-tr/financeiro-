# Meu Financeiro — PHP + SQLite

Aplicação de controle financeiro pessoal feita com HTML, CSS, JavaScript e PHP, usando SQLite em arquivo. Inclui autenticação privada, painel mensal, lançamentos, categorias, orçamentos, metas de economia, relatórios, exportação CSV, backup JSON e restauração.

## Funcionalidades incluídas

- Criação da primeira conta privada; depois disso, não existe cadastro público.
- Senhas armazenadas com `password_hash`.
- Sessão PHP com cookie `HttpOnly` e `SameSite=Lax`.
- Proteção CSRF para operações de escrita.
- Lançamentos de entrada e saída com data, categoria, forma de pagamento e observações.
- Dashboard mensal com totais, gráfico de despesas por categoria e progresso de metas.
- Filtro do histórico por mês e categoria; edição e exclusão.
- Categorias personalizáveis com cor.
- Orçamentos por categoria e mês.
- Metas de economia com valor-alvo, valor guardado e prazo.
- Relatórios mensais e exportação CSV.
- Backup JSON e restauração validada.
- Tema claro/escuro/sistema.
- Layout responsivo para celular e computador.

## Requisitos

- PHP 8.1+ recomendado.
- Extensão `PDO_SQLITE` habilitada no PHP.
- HTTPS habilitado no site publicado.
- Diretório privado gravável para o arquivo SQLite.

**Importante:** antes de publicar, confirme no painel ou com o suporte da InfinityFree que `pdo_sqlite` está habilitado no seu plano. A aplicação não poderá criar o banco se essa extensão estiver indisponível.

## Instalação local

1. Instale PHP 8.1+ com a extensão PDO SQLite.
2. Crie a estrutura:
   - `site/` para os arquivos públicos da aplicação.
   - `private/` no mesmo nível de `site/`, fora da raiz pública.
3. Garanta permissão de escrita em `private/` para o processo PHP.
4. Inicie um servidor PHP a partir da pasta `site`:
   ```bash
   php -S localhost:8000
   ```
5. Abra `http://localhost:8000`.
6. Na primeira visita, crie a conta. Use senha forte com pelo menos 10 caracteres.

O banco `private/financeiro.sqlite` e suas tabelas serão criados automaticamente na primeira chamada ao backend.

## Publicação na InfinityFree

1. Faça download e mantenha uma cópia local do ZIP.
2. Envie o conteúdo da pasta `site/` para a pasta pública do seu domínio (normalmente `htdocs`).
3. Envie a pasta `private/` para fora da pasta pública. O exemplo de configuração assume que `private/` fica como pasta irmã de `htdocs/`.
4. Se a estrutura da sua conta for diferente, abra `site/config.php` e ajuste `DB_PATH` para um caminho absoluto privado adequado. Não use caminho dentro da pasta pública.
5. Confirme que a pasta privada permite gravação pelo PHP.
6. Abra o site por HTTPS e crie a primeira conta.
7. Teste criar, editar e excluir um lançamento; crie uma meta e um orçamento; baixe e teste um backup.
8. Se houver erro de configuração, consulte o log PHP do provedor. Por segurança, a aplicação não exibe detalhes internos do servidor aos visitantes.

### Se a InfinityFree não permitir pasta privada gravável

Não coloque o SQLite em uma pasta pública. Se a hospedagem não permitir armazenar o arquivo fora da raiz pública com escrita pelo PHP, pare a instalação e escolha uma hospedagem que permita armazenamento privado persistente. O `.htaccess` reduz a exposição em servidores Apache, mas não substitui manter o banco fora da pasta pública.

## Backup e restauração

- Em **Configurações → Backup e restauração**, use **Baixar backup completo (JSON)**.
- Guarde o arquivo no computador ou celular, e de preferência em uma segunda localização segura.
- O CSV serve para abrir os lançamentos em planilhas; ele não é um backup completo.
- A restauração substitui os dados financeiros existentes da conta. Faça um backup atual antes de importar outro arquivo.
- Faça backups periódicos e antes de atualizar a aplicação.

O backup JSON inclui categorias, transações, metas e orçamentos da conta. O arquivo de banco SQLite também pode ser copiado quando a aplicação não está gravando; para backup consistente em uso, prefira o exportador JSON ou use as ferramentas próprias de backup SQLite.

## Segurança e limites

- O arquivo SQLite deve ficar fora da pasta pública.
- Não compartilhe a senha e não use uma senha que você já utiliza em outros sites.
- O código inclui proteção CSRF, consultas parametrizadas, `password_hash`, validação de entrada e filtros por usuário.
- A conta inicial é a única conta que pode ser criada pelo fluxo normal. O site foi pensado para uso pessoal.
- Antes de usar com dados financeiros reais, teste em ambiente de desenvolvimento e valide as permissões de arquivo e o suporte SQLite da hospedagem.
- Este projeto não substitui o backup independente nem garante disponibilidade da hospedagem gratuita.

## Estrutura

```text
controle-financeiro/
├── site/
│   ├── index.php
│   ├── api.php
│   ├── config.php
│   ├── .htaccess
│   └── assets/
│       ├── app.js
│       └── styles.css
├── private/
│   └── README.txt
└── README.md
```
