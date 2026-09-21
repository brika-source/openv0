IF OBJECT_ID(N'[__EFMigrationsHistory]') IS NULL
BEGIN
    CREATE TABLE [__EFMigrationsHistory] (
        [MigrationId] nvarchar(150) NOT NULL,
        [ProductVersion] nvarchar(32) NOT NULL,
        CONSTRAINT [PK___EFMigrationsHistory] PRIMARY KEY ([MigrationId])
    );
END;
GO

BEGIN TRANSACTION;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [Pillars] (
        [Id] nvarchar(64) NOT NULL,
        [Name] nvarchar(128) NOT NULL,
        [SortOrder] int NOT NULL,
        CONSTRAINT [PK_Pillars] PRIMARY KEY ([Id])
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [SerialCounters] (
        [Name] nvarchar(32) NOT NULL,
        [LastValue] int NOT NULL,
        CONSTRAINT [PK_SerialCounters] PRIMARY KEY ([Name])
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [Users] (
        [Id] nvarchar(64) NOT NULL,
        [Handle] nvarchar(64) NOT NULL,
        [Name] nvarchar(128) NOT NULL,
        [Email] nvarchar(256) NOT NULL,
        [IsProvisional] bit NOT NULL,
        CONSTRAINT [PK_Users] PRIMARY KEY ([Id])
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [WeekDates] (
        [WeekIndex] int NOT NULL,
        [StartDate] date NOT NULL,
        CONSTRAINT [PK_WeekDates] PRIMARY KEY ([WeekIndex])
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [Projects] (
        [Id] nvarchar(64) NOT NULL,
        [PillarId] nvarchar(64) NOT NULL,
        [Name] nvarchar(200) NOT NULL,
        [Scope] nvarchar(32) NULL,
        [DeptOwner] nvarchar(128) NULL,
        [DigitalOwnerId] nvarchar(64) NULL,
        [PmId] nvarchar(64) NULL,
        [Status] nvarchar(32) NOT NULL,
        [StartDate] date NULL,
        [DueDate] date NULL,
        [BaselineDate] date NULL,
        [ImplementationDate] date NULL,
        [ActualCompletionDate] date NULL,
        [CostAvoidance] decimal(18,2) NULL,
        [LabourHoursSaving] decimal(18,2) NULL,
        [ProductivityImprovement] decimal(18,2) NULL,
        [SavingsType] nvarchar(64) NULL,
        [CreatedAt] datetime2 NOT NULL,
        [UpdatedAt] datetime2 NOT NULL,
        CONSTRAINT [PK_Projects] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_Projects_Pillars_PillarId] FOREIGN KEY ([PillarId]) REFERENCES [Pillars] ([Id]) ON DELETE CASCADE,
        CONSTRAINT [FK_Projects_Users_DigitalOwnerId] FOREIGN KEY ([DigitalOwnerId]) REFERENCES [Users] ([Id]) ON DELETE NO ACTION,
        CONSTRAINT [FK_Projects_Users_PmId] FOREIGN KEY ([PmId]) REFERENCES [Users] ([Id]) ON DELETE NO ACTION
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [WorkItems] (
        [Id] nvarchar(64) NOT NULL,
        [ProjectId] nvarchar(64) NOT NULL,
        [Name] nvarchar(200) NOT NULL,
        [Notes] nvarchar(max) NULL,
        [SortOrder] int NOT NULL,
        CONSTRAINT [PK_WorkItems] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_WorkItems_Projects_ProjectId] FOREIGN KEY ([ProjectId]) REFERENCES [Projects] ([Id]) ON DELETE CASCADE
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [Actions] (
        [Id] nvarchar(64) NOT NULL,
        [WorkItemId] nvarchar(64) NOT NULL,
        [Serial] nvarchar(32) NOT NULL,
        [Name] nvarchar(400) NOT NULL,
        [Status] nvarchar(32) NOT NULL,
        [Priority] nvarchar(32) NOT NULL,
        [Quarter] nvarchar(32) NOT NULL,
        [Recurrence] nvarchar(32) NOT NULL,
        [Source] nvarchar(64) NOT NULL,
        [Target] nvarchar(400) NULL,
        [SuccessCriteria] nvarchar(max) NULL,
        [NextStep] nvarchar(max) NULL,
        [Notes] nvarchar(max) NULL,
        [CheckResult] nvarchar(max) NULL,
        [ReviewDate] date NULL,
        [CompletionDate] date NULL,
        [CreatedAt] datetime2 NOT NULL,
        [UpdatedAt] datetime2 NOT NULL,
        CONSTRAINT [PK_Actions] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_Actions_WorkItems_WorkItemId] FOREIGN KEY ([WorkItemId]) REFERENCES [WorkItems] ([Id]) ON DELETE CASCADE
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [ActionHistories] (
        [Id] nvarchar(64) NOT NULL,
        [ActionItemId] nvarchar(64) NOT NULL,
        [Field] nvarchar(64) NOT NULL,
        [FromValue] nvarchar(400) NULL,
        [ToValue] nvarchar(400) NULL,
        [By] nvarchar(128) NOT NULL,
        [At] datetime2 NOT NULL,
        CONSTRAINT [PK_ActionHistories] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_ActionHistories_Actions_ActionItemId] FOREIGN KEY ([ActionItemId]) REFERENCES [Actions] ([Id]) ON DELETE CASCADE
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [ActionOwners] (
        [Id] int NOT NULL IDENTITY,
        [ActionItemId] nvarchar(64) NOT NULL,
        [UserId] nvarchar(64) NOT NULL,
        CONSTRAINT [PK_ActionOwners] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_ActionOwners_Actions_ActionItemId] FOREIGN KEY ([ActionItemId]) REFERENCES [Actions] ([Id]) ON DELETE CASCADE,
        CONSTRAINT [FK_ActionOwners_Users_UserId] FOREIGN KEY ([UserId]) REFERENCES [Users] ([Id]) ON DELETE NO ACTION
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [ActionTags] (
        [Id] int NOT NULL IDENTITY,
        [ActionItemId] nvarchar(64) NOT NULL,
        [Tag] nvarchar(64) NOT NULL,
        CONSTRAINT [PK_ActionTags] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_ActionTags_Actions_ActionItemId] FOREIGN KEY ([ActionItemId]) REFERENCES [Actions] ([Id]) ON DELETE CASCADE
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [ActionWeeks] (
        [Id] int NOT NULL IDENTITY,
        [ActionItemId] nvarchar(64) NOT NULL,
        [WeekIndex] int NOT NULL,
        [Mark] nvarchar(8) NOT NULL,
        CONSTRAINT [PK_ActionWeeks] PRIMARY KEY ([Id]),
        CONSTRAINT [FK_ActionWeeks_Actions_ActionItemId] FOREIGN KEY ([ActionItemId]) REFERENCES [Actions] ([Id]) ON DELETE CASCADE
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE TABLE [Comments] (
        [Id] nvarchar(64) NOT NULL,
        [ActionItemId] nvarchar(64) NULL,
        [WorkItemId] nvarchar(64) NULL,
        [Author] nvarchar(128) NOT NULL,
        [Text] nvarchar(max) NOT NULL,
        [At] datetime2 NOT NULL,
        CONSTRAINT [PK_Comments] PRIMARY KEY ([Id]),
        CONSTRAINT [CK_Comments_SingleParent] CHECK (([ActionItemId] IS NOT NULL AND [WorkItemId] IS NULL) OR ([ActionItemId] IS NULL AND [WorkItemId] IS NOT NULL)),
        CONSTRAINT [FK_Comments_Actions_ActionItemId] FOREIGN KEY ([ActionItemId]) REFERENCES [Actions] ([Id]) ON DELETE CASCADE,
        CONSTRAINT [FK_Comments_WorkItems_WorkItemId] FOREIGN KEY ([WorkItemId]) REFERENCES [WorkItems] ([Id]) ON DELETE NO ACTION
    );
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_ActionHistories_ActionItemId_At] ON [ActionHistories] ([ActionItemId], [At]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_ActionOwners_ActionItemId_UserId] ON [ActionOwners] ([ActionItemId], [UserId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_ActionOwners_UserId] ON [ActionOwners] ([UserId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_Actions_Serial] ON [Actions] ([Serial]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Actions_Status] ON [Actions] ([Status]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Actions_WorkItemId] ON [Actions] ([WorkItemId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_ActionTags_ActionItemId_Tag] ON [ActionTags] ([ActionItemId], [Tag]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_ActionWeeks_ActionItemId_WeekIndex] ON [ActionWeeks] ([ActionItemId], [WeekIndex]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Comments_ActionItemId] ON [Comments] ([ActionItemId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Comments_WorkItemId] ON [Comments] ([WorkItemId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_Pillars_Name] ON [Pillars] ([Name]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Projects_DigitalOwnerId] ON [Projects] ([DigitalOwnerId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Projects_PillarId] ON [Projects] ([PillarId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Projects_PmId] ON [Projects] ([PmId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_Projects_Status] ON [Projects] ([Status]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_Users_Email] ON [Users] ([Email]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE UNIQUE INDEX [IX_Users_Handle] ON [Users] ([Handle]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    CREATE INDEX [IX_WorkItems_ProjectId] ON [WorkItems] ([ProjectId]);
END;
GO

IF NOT EXISTS (
    SELECT * FROM [__EFMigrationsHistory]
    WHERE [MigrationId] = N'20260921200555_InitialCreate'
)
BEGIN
    INSERT INTO [__EFMigrationsHistory] ([MigrationId], [ProductVersion])
    VALUES (N'20260921200555_InitialCreate', N'8.0.11');
END;
GO

COMMIT;
GO

