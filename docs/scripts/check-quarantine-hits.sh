#!/bin/bash
# Check 404s no nginx access.log apontando pra arquivos em quarantine.
# Roda em ~30 dias pra decidir: deletar de vez ou restore.
#
# Uso: sudo bash /usr/local/bin/cdm-check-quarantine.sh
# Output: lista de paths 404 que apontam pra uploads/2019/ ou /2021/

ACCESS_LOG="/var/log/nginx/ciadasmochilas.com.br.access.log"
ARCHIVES_PATTERN="$ACCESS_LOG*"

# Pega ultimos 30 dias de logs (rotacionados)
echo "=== 404s em uploads/2019/ ou /2021/ (ultimos 30d) ==="
echo "Source logs: $ARCHIVES_PATTERN"
echo ""

sudo zgrep -hE 'GET /wp-content/uploads/(2019|2021)/[^ ]+ HTTP[^ ]+" 404' $ARCHIVES_PATTERN 2>/dev/null | \
  awk '{
    for (i=1; i<=NF; i++) {
      if ($i ~ /^"GET/) {
        print $(i+1);
        break
      }
    }
  }' | sort | uniq -c | sort -rn | head -30

echo ""
echo "=== Resumo ==="
TOTAL_404=$(sudo zgrep -cE 'GET /wp-content/uploads/(2019|2021)/[^ ]+ HTTP[^ ]+" 404' $ARCHIVES_PATTERN 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')
UNIQUE_PATHS=$(sudo zgrep -hE 'GET /wp-content/uploads/(2019|2021)/[^ ]+ HTTP[^ ]+" 404' $ARCHIVES_PATTERN 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i ~ /^"GET/) {print $(i+1); break}}' | sort -u | wc -l)

echo "Total 404 hits:    $TOTAL_404"
echo "Paths unicos:      $UNIQUE_PATHS"
echo ""
echo "Quarantine size:"
sudo du -sh /var/quarantine/cdm-uploads-2026-05-22 2>/dev/null || echo "  (quarantine ja deletado)"

if [[ "$TOTAL_404" -eq 0 ]]; then
  echo ""
  echo "RECOMENDACAO: zero hits -> seguro deletar definitivo"
  echo "  sudo rm -rf /var/quarantine/cdm-uploads-2026-05-22"
  echo "  sudo rm /tmp/cdm-uploads-orphans-2026-05-22.tar.gz"
else
  echo ""
  echo "RECOMENDACAO: ha referencias ativas -> investigar antes de deletar"
  echo "  Pra restaurar arquivo especifico:"
  echo "  sudo cp /var/quarantine/cdm-uploads-2026-05-22/2019/XX/<file> /var/www/.../uploads/2019/XX/"
fi
