CREATE TABLE tx_udtotpauth_domain_model_totpsecret (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    
    fe_user int(11) unsigned DEFAULT '0' NOT NULL,
    secret varchar(255) DEFAULT '' NOT NULL,
    is_active tinyint(4) unsigned DEFAULT '0' NOT NULL,
    last_used_at int(11) DEFAULT '0' NOT NULL,
    
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY fe_user (fe_user)
);

CREATE TABLE tx_udtotpauth_domain_model_emailtoken (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    
    fe_user int(11) unsigned DEFAULT '0' NOT NULL,
    token varchar(64) DEFAULT '' NOT NULL,
    valid_until int(11) DEFAULT '0' NOT NULL,
    
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid),
    KEY fe_user (fe_user),
    UNIQUE KEY token_idx (token)
);