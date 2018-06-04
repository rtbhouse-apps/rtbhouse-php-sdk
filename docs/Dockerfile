FROM php:7.0-cli
ARG UNAME=apps
ARG UID=1000
ARG GID=1000

RUN groupadd -g $GID $UNAME && useradd -m -u $UID -g $GID -s /bin/bash $UNAME
RUN apt-get update && apt-get install -y wget unzip

USER $UNAME
ENV HOME="/home/$UNAME"
WORKDIR $HOME

RUN wget -q https://getcomposer.org/download/1.6.5/composer.phar && chmod +x composer.phar

RUN mkdir ./code
COPY --chown=apps:apps composer.* ./code/
WORKDIR $HOME/code
RUN $HOME/composer.phar install

ENTRYPOINT ["/usr/local/bin/php"]
CMD ["example.php"]
