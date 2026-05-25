# Scripts de manutencao — Cia das Mochilas

Backup version-controlled dos scripts ad-hoc que rodam no server `ciadasmochilas` (Hetzner). Source of truth fica no servidor (`/tmp/` ou `/usr/local/bin/`); este diretorio mirror-a no git pra historia + rollback.

## Workflow

```bash
# 1. Editar local
$EDITOR docs/scripts/<script>.sh

# 2. Deploy
scp docs/scripts/<script>.sh ciadasmochilas:/tmp/

# 3. Executar
ssh ciadasmochilas "chmod +x /tmp/<script>.sh && sudo bash /tmp/<script>.sh"

# 4. Commit
git add docs/scripts/<script>.sh
git commit -m "scripts: <descricao>"
```

## Inventario

### Cleanup de uploads orfaos (executado 2026-05-22)

| Script | Funcao |
|---|---|
| `scan-orphans-v2.php` | Dual-check (filename + attachment_id) pra identificar orfaos em uploads/2019-2021. Output: `/tmp/cdm-orphans-list.txt` |
| `quarantine-orphans.sh` | Move arquivos de `/tmp/cdm-orphans-list.txt` pra `/var/quarantine/cdm-uploads-YYYY-MM-DD/` preservando estrutura |
| `clean-orphan-attachments.php` | Limpa `wp_posts` (post_type=attachment) cujo `_wp_attached_file` aponta pra arquivo inexistente |
| `check-quarantine-hits.sh` | Em 30d, verifica 404s no nginx access.log pra paths em quarantine. Decisao: deletar ou restaurar |

Issue de followup: [#2](https://github.com/jtiberio-sv/cia-astro-theme/issues/2) (2026-06-21)

Detalhe canonico: `~/.claude/memory/reference_wp_uploads_orphan_cleanup.md`

### Otimizacao de imagens (executado 2026-05-22)

| Script | Funcao |
|---|---|
| `generate-missing-webp.sh` | Gera WebP Q75 pra JPG/PNG sem `.webp` correspondente. Usa `cwebp-linux` do EWWW plugin. 4 workers paralelos |
| `generate-avif.sh` | Gera AVIF QP 25-55 pra JPG/PNG >20KB sem `.avif`. Usa `/usr/bin/avifenc` (`libavif-bin`) |
| `recompress-top-banners.sh` | Recomprime originais JPG/PNG >400KB com `convert -q 85 -strip`. Cria `.bak` antes |

Issue de followup: [#4](https://github.com/jtiberio-sv/cia-astro-theme/issues/4) (2026-06-08) — deletar `.bak` files

### Cobertura pos-otimizacao (2026-05-22)

- WebP: JPG 100% / PNG 99%
- AVIF (>20KB): JPG 100% / PNG 76%
- Nginx serve automaticamente WebP/AVIF via `try_files $uri$avif_suffix $uri$webp_suffix $uri` (browser content negotiation)

## Padronizacao

- Sempre rodar com `sudo bash <script>.sh` (precisa write nos uploads)
- Logs em `/tmp/cdm-<feature>-YYYYMMDD-HHMMSS.log`
- Output stats: OK / SKIP / FAIL counts
- Background friendly: usar `nohup` + `&` quando ETA > 5min
