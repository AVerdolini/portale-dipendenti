#!/bin/sh
set -e

# /var/www/html is a named volume that persists across image rebuilds.
# /var/www/html-src is the application code baked into this image build.
# On every container start, sync the code from the image into the volume,
# without touching anything that is runtime-generated data rather than code.
rsync -a --delete \
    --exclude 'storage/' \
    --exclude 'vendor/' \
    /var/www/html-src/ /var/www/html/

rsync -a /var/www/html-src/vendor/ /var/www/html/vendor/

mkdir -p /var/www/html/storage/originali /var/www/html/storage/documenti

chown -R www-data:www-data /var/www/html

exec "$@"
