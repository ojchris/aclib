ARG BASE_IMAGE
FROM $BASE_IMAGE

ENV NVM_DIR=/usr/local/nvm
ENV NODE_DEFAULT_VERSION=v12.18.3

RUN curl -sL https://raw.githubusercontent.com/nvm-sh/nvm/v0.35.3/install.sh -o install_nvm.sh
RUN mkdir -p $NVM_DIR && bash install_nvm.sh
RUN echo "source $NVM_DIR/nvm.sh" >>/etc/profile
RUN bash -ic "nvm install $NODE_DEFAULT_VERSION && nvm use $NODE_DEFAULT_VERSION"
RUN chmod -R ugo+w $NVM_DIR
RUN npm install --global gulp-cli
