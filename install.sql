DROP TABLE wcf1_unkso_longevity_awards;

-- Create the Awards list table
CREATE TABLE wcf1_unkso_longevity_award (
    longevityAwardID INT(10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tierID INT(10) NOT NULL,
    months INT(10) NOT NULL
);
