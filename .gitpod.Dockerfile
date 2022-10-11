FROM gitpod/workspace-base:latest

RUN add-apt-repository ppa:ondrej/php -y && \
    curl -fsSL https://deb.nodesource.com/setup_16.x | bash - && \
    curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' | sudo -E bash && \
    sudo apt-get install -y \
    php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-xml php8.1-zip php8.1-opcache php8.1-mbstring php8.1-intl php8.1-cli \
    rsync \
    symfony-cli \
    nodejs && \
    sudo wget https://github.com/FriendsOfShopware/shopware-cli/releases/download/0.1.39/shopware-cli_linux_amd64.deb && \
    sudo dpkg -i shopware-cli_linux_amd64.deb && \
    sudo rm shopware-cli_linux_amd64.deb
