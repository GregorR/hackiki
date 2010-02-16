#!/bin/bash
# Copyright (C) 2010 Gregor Richards
# 
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
# 
# The above copyright notice and this permission notice shall be included in
# all copies or substantial portions of the Software.
# 
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
# THE SOFTWARE.

die() {
    echo "$@"
    exit 1
}

hchroot() {
    chroot "$HACKIKI_DIR" "$@"
}

hwch() {
    chroot "$HACKIKI_DIR" su www-data -c "$*"
}

# Check dependencies
DEPS="wget make ar"
for d in $DEPS
do
    $d --version >& /dev/null
    if [ "$?" = "127" ]
    then
        echo 'This installer requires '$d
        exit 1
    fi
done

HACKIKI_ARCH="i386"
if [ "$1" ]
then
    if [ "$1" = "--help" -o "$1" = "-h" -o "$1" = "help" ]
    then
        echo 'Use: installhackiki.sh [architecture=i386] [installation dir=/var/chroots/hackiki]'
        exit
    fi

    HACKIKI_ARCH="$1"
fi

HACKIKI_DIR="/var/chroots/hackiki"
if [ "$2" ]
then
    HACKIKI_DIR="$2"
fi

clear
echo 'Hackiki installer started'

# get debootstrap
echo '1) Getting debootstrap'

if [ ! -e /tmp/debootstrap.deb ]
then
    wget http://ftp.us.debian.org/debian/pool/main/d/debootstrap/debootstrap_1.0.10lenny1_all.deb -O /tmp/debootstrap.deb ||
        die "Failed to download debootstrap"
fi

# extract debootstrap
echo '2) Extracting debootstrap'
if [ ! -e /tmp/debootstrap/usr ]
then
    mkdir -p /tmp/debootstrap
    cd /tmp/debootstrap
    ar x /tmp/debootstrap.deb data.tar.gz &&
        tar zxf data.tar.gz &&
        rm -f data.tar.gz &&
        sed 's/\/usr/\/tmp\/debootstrap\/usr/g' -i /tmp/debootstrap/usr/sbin/* ||
        die "Failed to install debootstrap"
fi

# make the debootstrap
echo '3) Making the chroot'
if [ ! -e "$HACKIKI_DIR"/etc/debian_version ]
then
    mkdir -p "$HACKIKI_DIR"
    /tmp/debootstrap/usr/sbin/debootstrap --arch "$HACKIKI_ARCH" \
        lenny "$HACKIKI_DIR" ||
        die "Failed to debootstrap $HACKIKI_DIR"
fi

# set up the chroot
echo '4) Setting up the chroot'
for d in /dev /proc /sys
do
    umount "$HACKIKI_DIR$d" >& /dev/null
    mount -o bind $d "$HACKIKI_DIR$d" || die "Failed to mount $d"
done

# set up sources.list
echo '5) Setting up sources.list'
if [ ! "`grep 'deb-src' $HACKIKI_DIR/etc/apt/sources.list`" ]
then
    echo 'deb http://plash.beasts.org/packages debian-lenny/' >> \
        "$HACKIKI_DIR"/etc/apt/sources.list
    sed 's/^deb/deb-src/g' "$HACKIKI_DIR"/etc/apt/sources.list >> \
        "$HACKIKI_DIR"/etc/apt/sources.list
    hchroot aptitude update || die "Failed to apt update"
fi

# install deps
echo '6) Installing prerequisite software'
hchroot aptitude -y install plash lighttpd php5-cgi mercurial php-openid \
                            php5-dev dpkg-dev

# install pcntl
echo '7) Installing php-pcntl'
if [ ! -e "$HACKIKI_DIR"/usr/lib/php*/*/pcntl.so ]
then
    echo '#!/bin/bash -x
    cd
    mkdir -p php
    cd php || exit 1
    apt-get source php5
    cd php*/ext/pcntl || exit 1
    phpize
    ./configure --prefix=/usr &&
    make &&
    make install &&
    echo -e "[PHP]\nextension=pcntl.so" >> /etc/php5/cgi/php.ini' > \
        "$HACKIKI_DIR"/root/php-pcntl.sh
    hchroot sh /root/php-pcntl.sh
fi

# set up lighttpd
echo '8) Setting up lighttpd'
if [ ! -e "$HACKIKI_DIR"/etc/lighttpd/lighttpd-hackiki-configured ]
then
    sed 's/^# *"mod_rewrite",/"mod_rewrite",/g' -i "$HACKIKI_DIR"/etc/lighttpd/lighttpd.conf
    hchroot lighttpd-enable-mod cgi fastcgi

    echo '$HTTP["url"] =~ "^/lib" {
        url.access-deny = ("")
    }
    $HTTP["url"] =~ "^/.hg" {
        url.access-deny = ("")
    }

    url.rewrite-once= ( "^/wiki(.*)" => "/wiki.php$1",
                        "^/fs(.*)" => "/cgi-bin/fshg.cgi$1" )' >> \
        "$HACKIKI_DIR"/etc/lighttpd/lighttpd.conf

    touch "$HACKIKI_DIR"/etc/lighttpd/lighttpd-hackiki-configured
fi

# set up hackiki itself
echo '9) Installing hackiki'
if [ ! -e "$HACKIKI_DIR"/var/www/wiki.php ]
then
    hchroot rm -f /var/www/*
    hchroot chown www-data:www-data /var/www
    hwch hg clone https://codu.org/projects/hackiki/hg/ /var/www/hackiki
    hwch mv /var/www/hackiki/* /var/www/hackiki/.hg /var/www/
    hwch rmdir /var/www/hackiki
fi

# create the configuration directory
echo '10) Configuring hackiki'
if [ ! -e "$HACKIKI_DIR"/var/lib/hackiki/fs/.hg ]
then
    hchroot mkdir -p /var/lib/hackiki
    hchroot chown www-data:www-data /var/lib/hackiki
    hwch cp /var/www/lib/limits /var/lib/hackiki/limits
    hwch hg init /var/lib/hackiki/fs
fi

# configure mercurial
echo '11) Configuring mercurial'
if [ ! -e "$HACKIKI_DIR"/var/www/cgi-bin/fshg.cgi ]
then
    hwch mkdir -p /var/www/cgi-bin
    hwch cp /usr/share/doc/mercurial/examples/hgweb.cgi /var/www/cgi-bin/fshg.cgi
    hwch chmod 0755 /var/www/cgi-bin/fshg.cgi
    hchroot sed 's/\/path\/to\/repo/\/var\/lib\/hackiki\/fs/ ; s/repository name/Hackiki filesystem/' -i /var/www/cgi-bin/fshg.cgi
fi

# set up the scripts to run it
echo '12) Installing scripts'
if [ ! -e "$HACKIKI_DIR"/start.sh ]
then
    echo '#!/bin/bash
    cd "`dirname $0`"
    for d in dev proc sys
    do
        umount $d >& /dev/null
        mount -o bind /$d $d
    done
    chroot . /etc/init.d/lighttpd restart' > "$HACKIKI_DIR"/start.sh
    chmod 0755 "$HACKIKI_DIR"/start.sh
fi

# Finally, tell the user to set up their firewall
echo '

=========================
ATTENTION
=========================

You MUST configure your firewall to restrict users with high user IDs from
accessing the Internet. Otherwise, Hackiki pages will be able to steal all your
bandwidth! If you'\''re using shorewall, add this to your /etc/shorewall/rules:

REJECT  $FW     net     all     -       -       -       -       5500-2147483647

To run Hackiki, run '$HACKIKI_DIR'/start.sh . If you want it to run
automatically, add '$HACKIKI_DIR'/start.sh to /etc/rc.local . If you want to
configure anything else in Hackiki, chroot into its directory first: "chroot
'$HACKIKI_DIR' su - www-data"'
