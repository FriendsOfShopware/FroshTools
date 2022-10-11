FROM gitpod/workspace-full:latest

RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash && \
    sudo apt-get install -y php8.1-fpm rsync symfony-cli && \
    sudo wget https://github.com/FriendsOfShopware/shopware-cli/releases/download/0.1.39/shopware-cli_linux_amd64.deb && \
    sudo dpkg -i shopware-cli_linux_amd64.deb && \
    sudo rm shopware-cli_linux_amd64.deb
