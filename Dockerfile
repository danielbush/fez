FROM uqlibrary/docker-fpm56-fez:11

WORKDIR /var/app/current/
COPY . /var/app/current/
COPY .docker/etc/nginx/conf.d/ /etc/nginx/conf.d/
COPY .docker/etc/nginx/rules/ /etc/nginx/rules/

RUN cd /var/cache/ && \
    mkdir file && \
    mkdir solr_upload && \
    mkdir templates_c && \
    mkdir xdebug && \
    mkdir tmp && \
    mkdir /var/log/espacestage && \
    chown -R nobody /var/log/espacestage && \
    chown -R nobody /var/cache && \
    chmod -R 777 /var/cache && \
    mkdir -p /var/app/current/public/include/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer/HTML && \
    chmod -R 777 /var/app/current/public/include/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer/HTML && \
    chown -R nobody /var/app/current/public/include/htmlpurifier/library/HTMLPurifier/DefinitionCache/Serializer/HTML && \
    chmod -R 777 /var/app/current/public/templates_c && \
    chown -R nobody /var/app/current/public/templates_c

RUN chmod +x .docker/production/bootstrap.sh

VOLUME /var/app/current
VOLUME /var/cache
VOLUME /etc/nginx/conf.d
VOLUME /etc/nginx/rules

ENTRYPOINT [".docker/production/bootstrap.sh"]
