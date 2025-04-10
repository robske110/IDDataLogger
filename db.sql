CREATE TABLE IF NOT EXISTS carStatus (
  time                      TIMESTAMP NOT NULL PRIMARY KEY,
  batterySOC                decimal(3, 0),
  remainingRange            smallint,
  remainingChargingTime     smallint,
  chargeState               text,
  chargePower               decimal(4, 1),
  chargeRateKMPH            smallint,
  maxChargeCurrentAC        text,
  autoUnlockPlugWhenCharged text,
  targetSOC                 decimal(3, 0),
  plugConnectionState       text,
  plugLockState             text,
  remainClimatisationTime   smallint,
  hvacState                 text,
  hvacTargetTemp            decimal(3, 1),
  hvacWithoutExternalPower  boolean,
  hvacAtUnlock              boolean,
  windowHeatingEnabled      boolean,
  zoneFrontLeftEnabled      boolean,
  zoneFrontRightEnabled     boolean,
  zoneRearLeftEnabled       boolean,
  zoneRearRightEnabled      boolean,
  frontWindowHeatingState   text,
  rearWindowHeatingState    text,
  odometer                  integer,
  latitude		    decimal(8, 6),
  longitude		    decimal(9, 6)
);

CREATE TABLE IF NOT EXISTS users (
  userid   serial NOT NULL PRIMARY KEY,
  username text UNIQUE NOT NULL,
  hash     text NOT NULL
);

CREATE TABLE IF NOT EXISTS authKeys (
  authKey text UNIQUE NOT NULL
);

CREATE TABLE IF NOT EXISTS carPictures (
  pictureID  varchar(128) NOT NULL PRIMARY KEY,
  carPicture text NOT NULL
);

CREATE TABLE IF NOT EXISTS chargingSessions (
  sessionid       serial UNIQUE NOT NULL,
  startTime       TIMESTAMP NOT NULL PRIMARY KEY,
  endTime         TIMESTAMP,
  chargeStartTime TIMESTAMP,
  chargeEndTime   TIMESTAMP,
  duration        integer,
  avgChargePower  decimal(4, 1),
  maxChargePower  decimal(4, 1),
  minChargePower  decimal(4, 1),
  chargeEnergy    float4,
  rangeStart      smallint,
  rangeEnd        smallint,
  targetSOC       smallint,
  socStart        decimal(3, 0),
  socEnd          decimal(3, 0)
);

CREATE TABLE IF NOT EXISTS settings (
  settingKey   varchar(128) NOT NULL PRIMARY KEY,
  settingValue text
)
