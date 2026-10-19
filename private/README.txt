PASTA PRIVADA DO BANCO SQLITE

Na InfinityFree, não coloque esta pasta dentro de htdocs ou da pasta pública do site.
Envie-a para um diretório privado fora da raiz pública. O config.php assume:
  /caminho/da/conta/htdocs/  <- arquivos da pasta site/
  /caminho/da/conta/private/ <- esta pasta

O arquivo financeiro.sqlite será criado automaticamente pelo PHP quando o diretório existir
e tiver permissão de escrita para o usuário PHP. Não é necessário enviar um banco vazio.
Nunca exponha esta pasta pela web.
