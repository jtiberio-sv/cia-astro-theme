#!/bin/bash
# Gera WebP pras imagens JPG/PNG que ainda nao tem .webp correspondente.
# Usa cwebp-linux empacotado pelo EWWW Image Optimizer plugin.
# Roda em paralelo (4 workers) pra ~10x speedup.

set -uo pipefail

UPLOADS="/var/www/ciadasmochilas.com.br/htdocs/wp-content/uploads"
CWEBP="/var/www/ciadasmochilas.com.br/htdocs/wp-content/plugins/ewww-image-optimizer/binaries/cwebp-linux"
LOG="/tmp/cdm-webp-gen-$(date +%Y%m%d-%H%M%S).log"
QUALITY=75

if [[ ! -x "$CWEBP" ]]; then
  echo "ERROR: cwebp nao encontrado em $CWEBP"
  exit 1
fi

# Garante exec permission
sudo chmod +x "$CWEBP" 2>/dev/null

cd "$UPLOADS"
echo "=== WebP generation $(date) ===" | tee "$LOG"
echo "Quality: $QUALITY | Workers: 4" | tee -a "$LOG"

# Encontra todos JPG/JPEG/PNG sem .webp correspondente
echo "Scanning..." | tee -a "$LOG"
candidates=$(find . -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) ! -exec test -f '{}.webp' \; -print 2>/dev/null)
total=$(echo "$candidates" | grep -cv '^$' || echo 0)
echo "Candidatos: $total arquivos" | tee -a "$LOG"

if [[ "$total" -eq 0 ]]; then
  echo "Nada a converter." | tee -a "$LOG"
  exit 0
fi

# Processa em paralelo (4 jobs simultaneos)
echo "$candidates" | xargs -P 4 -I {} bash -c '
  src="$1"
  dst="${src}.webp"
  if "'"$CWEBP"'" -q '"$QUALITY"' -m 4 -quiet "$src" -o "$dst" 2>/dev/null; then
    orig=$(stat -c%s "$src" 2>/dev/null || echo 0)
    new=$(stat -c%s "$dst" 2>/dev/null || echo 0)
    if [[ "$new" -gt 0 ]] && [[ "$new" -lt "$orig" ]]; then
      echo "OK $src ${orig}b -> ${new}b" >> "'"$LOG"'"
    else
      # Se WebP ficou maior, remove (mantem original)
      rm -f "$dst"
      echo "SKIP $src (webp maior que original)" >> "'"$LOG"'"
    fi
  else
    echo "FAIL $src" >> "'"$LOG"'"
  fi
' _ {}

# Stats
ok=$(grep -c '^OK ' "$LOG" 2>/dev/null || echo 0)
skip=$(grep -c '^SKIP ' "$LOG" 2>/dev/null || echo 0)
fail=$(grep -c '^FAIL ' "$LOG" 2>/dev/null || echo 0)
total_orig=$(grep '^OK ' "$LOG" | awk -F'->' '{gsub(/[^0-9]/,"",$1); s+=$1} END {print s+0}')
total_new=$(grep '^OK ' "$LOG" | awk -F'->' '{gsub(/[^0-9]/,"",$2); s+=$2} END {print s+0}')

echo "" | tee -a "$LOG"
echo "=== RESULTADO ===" | tee -a "$LOG"
echo "OK:   $ok arquivos convertidos" | tee -a "$LOG"
echo "SKIP: $skip (WebP ficou maior que original)" | tee -a "$LOG"
echo "FAIL: $fail" | tee -a "$LOG"
if [[ "$total_orig" -gt 0 ]]; then
  saving=$((total_orig - total_new))
  pct=$((saving * 100 / total_orig))
  echo "Economia: $(echo "scale=1; $saving/1024/1024" | bc) MB ($pct%)" | tee -a "$LOG"
  echo "Original: $(echo "scale=1; $total_orig/1024/1024" | bc) MB" | tee -a "$LOG"
  echo "Novo:     $(echo "scale=1; $total_new/1024/1024" | bc) MB" | tee -a "$LOG"
fi
echo "" | tee -a "$LOG"
echo "Log completo: $LOG" | tee -a "$LOG"

# Set ownership pra www-data nos novos .webp
sudo find "$UPLOADS" -name '*.webp' -newer "$LOG" -exec chown www-data:www-data {} \; 2>/dev/null
