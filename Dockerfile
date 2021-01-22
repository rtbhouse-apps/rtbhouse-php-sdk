ARG PHP_VERSION
FROM php:$PHP_VERSION-cli
ARG UNAME=apps
ARG UID=1000
ARG GID=1000

RUN groupadd -g $GID $UNAME && useradd -m -u $UID -g $GID -s /bin/bash $UNAME
RUN apt-get update && apt-get install -y wget unzip

USER $UNAME
ENV HOME="/home/$UNAME"
WORKDIR $HOME

COPY --chown=apps:apps ./bin/install_composer.sh .
RUN ./install_composer.sh

RUN mkdir ./code
COPY --chown=apps:apps composer.* ./code/
WORKDIR $HOME/code
RUN $HOME/composer.phar install
COPY --chown=apps:apps src/* ./src/
COPY --chown=apps:apps tests/* ./tests/

ENTRYPOINT ["/usr/local/bin/php"]
CMD ["./vendor/bin/phpunit", "--log-junit", "results/results.xml", "tests"]
