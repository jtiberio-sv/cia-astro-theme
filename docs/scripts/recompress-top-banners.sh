#!/bin/bash
# Recomprime top JPG/PNG > 400KB com Imagick (mantém .jpg/.png mas reduz tamanho).
# Backup .bak antes pra rollback. WebP/AVIF gerados em separado.
# Foco em browsers legacy que nao aceitam WebP/AVIF.

set -uo pipefail

UPLOADS="/var/www/ciadasmochilas.com.br/htdocs/wp-content/uploads"
LOG="/tmp/cdm-recompress-$(date +%Y%m%d-%H%M%S).log"
THRESHOLD=400000  # 400 KB - so recomprime arquivos maiores que isso
QUALITY=85        # JPG quality 85 = visually lossless

cd "$UPLOADS"
echo "=== Recompress top images $(date) ===" | tee "$LOG"
echo "Threshold: >${THRESHOLD}b | Quality: $QUALITY" | tee -a "$LOG"

# Lista candidatos
candidates=$(find . -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) -size +${THRESHOLD}c 2>/dev/null)
total=$(echo "$candidates" | grep -cv '^$' || echo 0)
echo "Candidatos (>400KB): $total" | tee -a "$LOG"

if [[ "$total" -eq 0 ]]; then
  echo "Nada a recomprimir."
  exit 0
fi

ok=0; skip=0; fail=0
saving_total=0

while IFS= read -r src; do
  [[ -z "$src" ]] && continue
  orig_size=$(stat -c%s "$src" 2>/dev/null || echo 0)
  [[ "$orig_size" -eq 0 ]] && continue

  # Backup
  if [[ ! -f "${src}.bak" ]]; then
    sudo cp "$src" "${src}.bak"
  fi

  # Detecta tipo
  ext="${src##*.}"
  ext_lower="${ext,,}"

  tmp="/tmp/recompress-$$"
  if [[ "$ext_lower" == "png" ]]; then
    # PNG: usa optipng pra lossless
    sudo cp "$src" "$tmp.png"
    /var/www/ciadasmochilas.com.br/htdocs/wp-content/plugins/ewww-image-optimizer/binaries/optipng-linux -o3 -quiet "$tmp.png" 2>/dev/null
    new_size=$(stat -c%s "$tmp.png" 2>/dev/null || echo 0)
    if [[ "$new_size" -gt 0 ]] && [[ "$new_size" -lt $((orig_size * 90 / 100)) ]]; then
      sudo cp "$tmp.png" "$src"
      sudo chown www-data:www-data "$src"
      saving=$((orig_size - new_size))
      saving_total=$((saving_total + saving))
      ok=$((ok+1))
      echo "OK PNG $src ${orig_size}b -> ${new_size}b (-${saving}b)" >> "$LOG"
    else
      sudo rm -f "${src}.bak"
      skip=$((skip+1))
      echo "SKIP PNG $src (no significant saving)" >> "$LOG"
    fi
    rm -f "$tmp.png"
  else
    # JPG: usa convert (ImageMagick) com strip + quality 85
    if convert "$src" -strip -interlace Plane -sampling-factor 4:2:0 -quality $QUALITY "$tmp.jpg" 2>/dev/null; then
      new_size=$(stat -c%s "$tmp.jpg" 2>/dev/null || echo 0)
      if [[ "$new_size" -gt 0 ]] && [[ "$new_size" -lt $((orig_size * 85 / 100)) ]]; then
        sudo cp "$tmp.jpg" "$src"
        sudo chown www-data:www-data "$src"
        saving=$((orig_size - new_size))
        saving_total=$((saving_total + saving))
        ok=$((ok+1))
        echo "OK JPG $src ${orig_size}b -> ${new_size}b (-${saving}b)" >> "$LOG"
      else
        sudo rm -f "${src}.bak"
        skip=$((skip+1))
        echo "SKIP JPG $src (no significant saving)" >> "$LOG"
      fi
      rm -f "$tmp.jpg"
    else
      sudo rm -f "${src}.bak"
      fail=$((fail+1))
      echo "FAIL $src" >> "$LOG"
    fi
  fi
done <<< "$candidates"

echo "" | tee -a "$LOG"
echo "=== RESULTADO ===" | tee -a "$LOG"
echo "OK:   $ok arquivos recomprimidos" | tee -a "$LOG"
echo "SKIP: $skip (sem ganho significativo)" | tee -a "$LOG"
echo "FAIL: $fail" | tee -a "$LOG"
echo "Economia total: $(echo "scale=1; $saving_total/1024/1024" | bc) MB" | tee -a "$LOG"
