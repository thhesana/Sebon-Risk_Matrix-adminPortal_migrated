-- Table: masterListCompanyNamSect
-- PK starts at 1001
USE [RiskMatrix_AML];
GO

IF NOT EXISTS (
    SELECT 1
    FROM sys.tables
    WHERE name = N'masterListCompanyNamSect'
      AND schema_id = SCHEMA_ID(N'dbo')
)
BEGIN
    CREATE TABLE [dbo].[masterListCompanyNamSect] (
        [masterListCompanyNamSect]        INT IDENTITY(1001, 1) NOT NULL,
        [masterListCompanyNamSect_name]   NVARCHAR(255)         NOT NULL,
        [SectorId]                        INT                   NULL,
        [masterListCompanyNamSect_Created] DATETIME             NOT NULL
            CONSTRAINT [DF_masterListCompanyNamSect_Created] DEFAULT (GETDATE()),
        CONSTRAINT [PK_masterListCompanyNamSect]
            PRIMARY KEY CLUSTERED ([masterListCompanyNamSect] ASC)
    );
END
GO
