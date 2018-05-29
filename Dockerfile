FROM php:7-cli
ARG UNAME=apps
ARG UID=1000
ARG GID=1000

RUN groupadd -g $GID $UNAME && useradd -m -u $UID -g $GID -s /bin/bash $UNAME
RUN apt-get update && apt-get install -y wget

USER $UNAME
ENV HOME="/home/$UNAME"
WORKDIR $HOME/code

ENTRYPOINT ["/usr/local/bin/php"]
CMD ["./src/hello.php" ]
