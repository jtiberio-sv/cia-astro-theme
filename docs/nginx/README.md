# Nginx configs — Cia das Mochilas (loja)

Backup version-controlled das configs nginx custom da loja. Source of truth fica no servidor em `/var/www/ciadasmochilas.com.br/conf/nginx/`; este diretorio é mirror pra ter o conteudo no git.

## Workflow

Editar **sempre direto no servidor** + atualizar este backup:

```bash
# 1. Editar local
$EDITOR docs/nginx/cia-redirects.conf

# 2. Deploy
scp docs/nginx/cia-redirects.conf ciadasmochilas:/tmp/
ssh ciadasmochilas "sudo cp /tmp/cia-redirects.conf /var/www/ciadasmochilas.com.br/conf/nginx/ && \
  sudo chown root:root /var/www/ciadasmochilas.com.br/conf/nginx/cia-redirects.conf && \
  sudo nginx -t && sudo systemctl reload nginx"

# 3. Commit + push
git add docs/nginx/cia-redirects.conf
git commit -m "nginx: <descricao da mudanca>"
```

## Arquivos

| Arquivo | Funcao |
|---|---|
| `cia-redirects.conf` | Blindagem da loja: redireciona 301 paths do front pra vitrine + aliases canonicos. Ver doc na propria header do arquivo + ARCHITECTURE.md seção 6.5 |

## Validacao apos mudanca

```bash
ssh ciadasmochilas "sudo nginx -t"  # valida sintaxe

# Smoke test (espera 301 -> vitrine):
curl -s -o /dev/null -w "%{http_code} -> %{redirect_url}\n" --max-redirs 0 \
  https://loja.ciadasmochilas.com.br/produto/teste/

# Smoke test (espera 200, NAO redirect):
curl -s -o /dev/null -w "%{http_code}\n" \
  https://loja.ciadasmochilas.com.br/carrinho/
```
