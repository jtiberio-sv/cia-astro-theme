#!/bin/bash
# Gera AVIF pras imagens JPG/PNG > 20KB que ainda nao tem .avif correspondente.
# AVIF ~30% menor que WebP. Nginx try_files ja serve AVIF quando browser aceita.
# Roda em paralelo (4 workers).

set -uo pipefail

UPLOADS="/var/www/ciadasmochilas.com.br/htdocs/wp-content/uploads"
AVIFENC="/usr/bin/avifenc"
LOG="/tmp/cdm-avif-gen-$(date +%Y%m%d-%H%M%S).log"

# AVIF quality: 0=lossless, 63=worst. ~50 = boa qualidade visual (~ WebP Q82)
# --min/--max: range de QP por bloco
QMIN=25
QMAX=55
SPEED=6   # 0=slow/best, 10=fast/worst. 6 = balanceado
MIN_SIZE=20480  # 20 KB - skip arquivos muito pequenos (jah otimizados, AVIF overhead)

cd "$UPLOADS"
echo "=== AVIF generation $(date) ===" | tee "$LOG"
echo "Quality range: $QMIN-$QMAX | Speed: $SPEED | Min size: ${MIN_SIZE}b | Workers: 4" | tee -a "$LOG"

# Encontra JPG/JPEG/PNG > 20KB sem .avif correspondente
echo "Scanning..." | tee -a "$LOG"
candidates=$(find . -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) -size +${MIN_SIZE}c ! -exec test -f '{}.avif' \; -print 2>/dev/null)
total=$(echo "$candidates" | grep -cv '^$' || echo 0)
echo "Candidatos: $total arquivos (>20KB sem AVIF)" | tee -a "$LOG"

if [[ "$total" -eq 0 ]]; then
  echo "Nada a converter." | tee -a "$LOG"
  exit 0
fi

# Processa em paralelo (4 jobs)
echo "$candidates" | xargs -P 4 -I {} bash -c '
  src="$1"
  dst="${src}.avif"
  if "'"$AVIFENC"'" --min '"$QMIN"' --max '"$QMAX"' --speed '"$SPEED"' --jobs 2 "$src" "$dst" >/dev/null 2>&1; then
    orig=$(stat -c%s "$src" 2>/dev/null || echo 0)
    new=$(stat -c%s "$dst" 2>/dev/null || echo 0)
    if [[ "$new" -gt 0 ]] && [[ "$new" -lt "$orig" ]]; then
      echo "OK $src ${orig}b -> ${new}b" >> "'"$LOG"'"
    else
      rm -f "$dst"
      echo "SKIP $src (avif maior)" >> "'"$LOG"'"
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
echo "OK:   $ok" | tee -a "$LOG"
echo "SKIP: $skip" | tee -a "$LOG"
echo "FAIL: $fail" | tee -a "$LOG"
if [[ "$total_orig" -gt 0 ]]; then
  saving=$((total_orig - total_new))
  pct=$((saving * 100 / total_orig))
  echo "Tamanho original: $(echo "scale=1; $total_orig/1024/1024" | bc) MB" | tee -a "$LOG"
  echo "Tamanho AVIF:     $(echo "scale=1; $total_new/1024/1024" | bc) MB" | tee -a "$LOG"
  echo "Economia:         $(echo "scale=1; $saving/1024/1024" | bc) MB ($pct%)" | tee -a "$LOG"
fi
echo "Log: $LOG" | tee -a "$LOG"

sudo find "$UPLOADS" -name '*.avif' -newer "$LOG" -exec chown www-data:www-data {} \; 2>/dev/null
