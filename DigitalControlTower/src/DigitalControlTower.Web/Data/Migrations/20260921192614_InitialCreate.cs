using System;
using Microsoft.EntityFrameworkCore.Migrations;

#nullable disable

namespace DigitalControlTower.Web.Data.Migrations
{
    /// <inheritdoc />
    public partial class InitialCreate : Migration
    {
        /// <inheritdoc />
        protected override void Up(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.CreateTable(
                name: "Pillars",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Name = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: false),
                    SortOrder = table.Column<int>(type: "int", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_Pillars", x => x.Id);
                });

            migrationBuilder.CreateTable(
                name: "SerialCounters",
                columns: table => new
                {
                    Name = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    LastValue = table.Column<int>(type: "int", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_SerialCounters", x => x.Name);
                });

            migrationBuilder.CreateTable(
                name: "Users",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Name = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: false),
                    Email = table.Column<string>(type: "nvarchar(256)", maxLength: 256, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_Users", x => x.Id);
                });

            migrationBuilder.CreateTable(
                name: "WeekDates",
                columns: table => new
                {
                    WeekIndex = table.Column<int>(type: "int", nullable: false),
                    StartDate = table.Column<DateOnly>(type: "date", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_WeekDates", x => x.WeekIndex);
                });

            migrationBuilder.CreateTable(
                name: "Projects",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    PillarId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Name = table.Column<string>(type: "nvarchar(200)", maxLength: 200, nullable: false),
                    Scope = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: true),
                    DeptOwner = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: true),
                    DigitalOwnerId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: true),
                    Pm = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: true),
                    Status = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    StartDate = table.Column<DateOnly>(type: "date", nullable: true),
                    DueDate = table.Column<DateOnly>(type: "date", nullable: true),
                    BaselineDate = table.Column<DateOnly>(type: "date", nullable: true),
                    ImplementationDate = table.Column<DateOnly>(type: "date", nullable: true),
                    ActualCompletionDate = table.Column<DateOnly>(type: "date", nullable: true),
                    CostAvoidance = table.Column<decimal>(type: "decimal(18,2)", nullable: true),
                    LabourHoursSaving = table.Column<decimal>(type: "decimal(18,2)", nullable: true),
                    ProductivityImprovement = table.Column<decimal>(type: "decimal(18,2)", nullable: true),
                    SavingsType = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: true),
                    CreatedAt = table.Column<DateTime>(type: "datetime2", nullable: false),
                    UpdatedAt = table.Column<DateTime>(type: "datetime2", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_Projects", x => x.Id);
                    table.ForeignKey(
                        name: "FK_Projects_Pillars_PillarId",
                        column: x => x.PillarId,
                        principalTable: "Pillars",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_Projects_Users_DigitalOwnerId",
                        column: x => x.DigitalOwnerId,
                        principalTable: "Users",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.SetNull);
                });

            migrationBuilder.CreateTable(
                name: "WorkItems",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    ProjectId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Name = table.Column<string>(type: "nvarchar(200)", maxLength: 200, nullable: false),
                    Notes = table.Column<string>(type: "nvarchar(max)", nullable: true),
                    SortOrder = table.Column<int>(type: "int", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_WorkItems", x => x.Id);
                    table.ForeignKey(
                        name: "FK_WorkItems_Projects_ProjectId",
                        column: x => x.ProjectId,
                        principalTable: "Projects",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "Actions",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    WorkItemId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Serial = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    Name = table.Column<string>(type: "nvarchar(400)", maxLength: 400, nullable: false),
                    Owner = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: true),
                    Status = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    Priority = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    Quarter = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    Recurrence = table.Column<string>(type: "nvarchar(32)", maxLength: 32, nullable: false),
                    Source = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Target = table.Column<string>(type: "nvarchar(400)", maxLength: 400, nullable: true),
                    SuccessCriteria = table.Column<string>(type: "nvarchar(max)", nullable: true),
                    NextStep = table.Column<string>(type: "nvarchar(max)", nullable: true),
                    Notes = table.Column<string>(type: "nvarchar(max)", nullable: true),
                    CheckResult = table.Column<string>(type: "nvarchar(max)", nullable: true),
                    ReviewDate = table.Column<DateOnly>(type: "date", nullable: true),
                    CompletionDate = table.Column<DateOnly>(type: "date", nullable: true),
                    CreatedAt = table.Column<DateTime>(type: "datetime2", nullable: false),
                    UpdatedAt = table.Column<DateTime>(type: "datetime2", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_Actions", x => x.Id);
                    table.ForeignKey(
                        name: "FK_Actions_WorkItems_WorkItemId",
                        column: x => x.WorkItemId,
                        principalTable: "WorkItems",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "ActionHistories",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    ActionItemId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Field = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    FromValue = table.Column<string>(type: "nvarchar(400)", maxLength: 400, nullable: true),
                    ToValue = table.Column<string>(type: "nvarchar(400)", maxLength: 400, nullable: true),
                    By = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: false),
                    At = table.Column<DateTime>(type: "datetime2", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_ActionHistories", x => x.Id);
                    table.ForeignKey(
                        name: "FK_ActionHistories_Actions_ActionItemId",
                        column: x => x.ActionItemId,
                        principalTable: "Actions",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "ActionTags",
                columns: table => new
                {
                    Id = table.Column<int>(type: "int", nullable: false)
                        .Annotation("SqlServer:Identity", "1, 1"),
                    ActionItemId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    Tag = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_ActionTags", x => x.Id);
                    table.ForeignKey(
                        name: "FK_ActionTags_Actions_ActionItemId",
                        column: x => x.ActionItemId,
                        principalTable: "Actions",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "ActionWeeks",
                columns: table => new
                {
                    Id = table.Column<int>(type: "int", nullable: false)
                        .Annotation("SqlServer:Identity", "1, 1"),
                    ActionItemId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    WeekIndex = table.Column<int>(type: "int", nullable: false),
                    Mark = table.Column<string>(type: "nvarchar(8)", maxLength: 8, nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_ActionWeeks", x => x.Id);
                    table.ForeignKey(
                        name: "FK_ActionWeeks_Actions_ActionItemId",
                        column: x => x.ActionItemId,
                        principalTable: "Actions",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                });

            migrationBuilder.CreateTable(
                name: "Comments",
                columns: table => new
                {
                    Id = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: false),
                    ActionItemId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: true),
                    WorkItemId = table.Column<string>(type: "nvarchar(64)", maxLength: 64, nullable: true),
                    Author = table.Column<string>(type: "nvarchar(128)", maxLength: 128, nullable: false),
                    Text = table.Column<string>(type: "nvarchar(max)", nullable: false),
                    At = table.Column<DateTime>(type: "datetime2", nullable: false)
                },
                constraints: table =>
                {
                    table.PrimaryKey("PK_Comments", x => x.Id);
                    table.CheckConstraint("CK_Comments_SingleParent", "([ActionItemId] IS NOT NULL AND [WorkItemId] IS NULL) OR ([ActionItemId] IS NULL AND [WorkItemId] IS NOT NULL)");
                    table.ForeignKey(
                        name: "FK_Comments_Actions_ActionItemId",
                        column: x => x.ActionItemId,
                        principalTable: "Actions",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Cascade);
                    table.ForeignKey(
                        name: "FK_Comments_WorkItems_WorkItemId",
                        column: x => x.WorkItemId,
                        principalTable: "WorkItems",
                        principalColumn: "Id",
                        onDelete: ReferentialAction.Restrict);
                });

            migrationBuilder.CreateIndex(
                name: "IX_ActionHistories_ActionItemId_At",
                table: "ActionHistories",
                columns: new[] { "ActionItemId", "At" });

            migrationBuilder.CreateIndex(
                name: "IX_Actions_Owner",
                table: "Actions",
                column: "Owner");

            migrationBuilder.CreateIndex(
                name: "IX_Actions_Serial",
                table: "Actions",
                column: "Serial",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_Actions_Status",
                table: "Actions",
                column: "Status");

            migrationBuilder.CreateIndex(
                name: "IX_Actions_WorkItemId",
                table: "Actions",
                column: "WorkItemId");

            migrationBuilder.CreateIndex(
                name: "IX_ActionTags_ActionItemId_Tag",
                table: "ActionTags",
                columns: new[] { "ActionItemId", "Tag" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_ActionWeeks_ActionItemId_WeekIndex",
                table: "ActionWeeks",
                columns: new[] { "ActionItemId", "WeekIndex" },
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_Comments_ActionItemId",
                table: "Comments",
                column: "ActionItemId");

            migrationBuilder.CreateIndex(
                name: "IX_Comments_WorkItemId",
                table: "Comments",
                column: "WorkItemId");

            migrationBuilder.CreateIndex(
                name: "IX_Pillars_Name",
                table: "Pillars",
                column: "Name",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_Projects_DigitalOwnerId",
                table: "Projects",
                column: "DigitalOwnerId");

            migrationBuilder.CreateIndex(
                name: "IX_Projects_PillarId",
                table: "Projects",
                column: "PillarId");

            migrationBuilder.CreateIndex(
                name: "IX_Projects_Status",
                table: "Projects",
                column: "Status");

            migrationBuilder.CreateIndex(
                name: "IX_Users_Email",
                table: "Users",
                column: "Email",
                unique: true);

            migrationBuilder.CreateIndex(
                name: "IX_WorkItems_ProjectId",
                table: "WorkItems",
                column: "ProjectId");
        }

        /// <inheritdoc />
        protected override void Down(MigrationBuilder migrationBuilder)
        {
            migrationBuilder.DropTable(
                name: "ActionHistories");

            migrationBuilder.DropTable(
                name: "ActionTags");

            migrationBuilder.DropTable(
                name: "ActionWeeks");

            migrationBuilder.DropTable(
                name: "Comments");

            migrationBuilder.DropTable(
                name: "SerialCounters");

            migrationBuilder.DropTable(
                name: "WeekDates");

            migrationBuilder.DropTable(
                name: "Actions");

            migrationBuilder.DropTable(
                name: "WorkItems");

            migrationBuilder.DropTable(
                name: "Projects");

            migrationBuilder.DropTable(
                name: "Pillars");

            migrationBuilder.DropTable(
                name: "Users");
        }
    }
}
