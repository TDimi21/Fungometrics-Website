import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "../js/store/auth";
import { useUserStore } from "../js/store/user";
import { useAccessStore } from "../js/store/access";
import { getAuthToken } from "../js/utils/authToken.js";
import { canManageSubscriptions } from "../js/utils/administration.js";

//Guest components
const Welcome = () => import("@/pages/Welcome.vue");

const PasswordRecoverEmail = () => import('@/pages/recover-password/Email.vue');
const PasswordRecoverPassword = () => import('@/pages/recover-password/Password.vue');
const LoginCoach = () => import("@/pages/login/Coach.vue");
const LoginPlayer = () => import("@/pages/login/Player.vue");
const RegisterCoach = () => import("@/pages/register/Coach.vue");
const RegisterPlayer = () => import("@/pages/register/Player.vue");
const RegisterComplete = () => import('@/pages/register/Complete.vue');
const DashBoard = () => import("@/pages/dashboard/Index.vue");
const AssessmentReports = () => import("@/pages/dashboard/AssessmentReports.vue");
const IndexTrainingPage = () => import("@/pages/training/CreateTraining.vue");
const IndexTrainingMode = () =>
	import("@/pages/training/CreateTrainingMode.vue");
const IndexTrainingCage = () =>
	import("@/pages/training/CreateTrainingCage.vue");
const IndexTrainingABPage = () =>
	import("@/pages/training/CreateLiveABTraining.vue");
const TrackBatting = () => import("@/pages/training/Batting.vue");
const TrackBullpen = () => import("@/pages/training/Bullpen.vue");
const TrackTrainingMode = () => import("@/pages/training/TrainingMode.vue");
const TrackTrainingCage = () => import("@/pages/training/TrainingCage.vue");
const Roster = () => import("@/pages/roster/HomeRoster.vue");
const PracticePlanner = () => import("@/pages/practice/PracticePlanner.vue");
const Manage = () => import("@/pages/manage/HomeManage.vue");
const CreateTeam = () => import("@/pages/manage/CreateTeam.vue");
const EditProfile = () => import("@/pages/profile/EditProfile.vue");
const EditProfilePlayer = () => import("@/pages/profile/EditProfilePlayer.vue");
const Settings = () => import("@/pages/profile/Settings.vue");
const DataHubDashboard = () => import("@/pages/data-hub/DataHubDashboard.vue");
const ImportData = () => import("@/pages/data-hub/ImportData.vue");
const UnknownColumns = () => import("@/pages/data-hub/UnknownColumns.vue");
const PlayerImportedMetrics = () => import("@/pages/data-hub/PlayerImportedMetrics.vue");
const BlastSessionDevelopmentReportPage = () => import("@/pages/data-hub/BlastSessionDevelopmentReportPage.vue");
const RapsodoPitchingSessionReportPage = () => import("@/pages/data-hub/RapsodoPitchingSessionReportPage.vue");
const ChangePassword = () => import("@/pages/profile/ChangePassword.vue");
const EditPlayer = () => import("@/pages/roster/EditPlayer.vue");
const TrackLiveAB = () => import("@/pages/training/LiveAB.vue");
const NewStatistic = () => import("@/pages/statistics/NewStatistic.vue");
const NewStatsSessionView = () => import("@/pages/statistics/NewStatsSessionView.vue");
const PlayerDevelopmentDashboard = () => import('@/features/development/pages/PlayerDevelopmentDashboard.vue');
const TeamDevelopmentDashboard = () => import('@/features/development/pages/TeamDevelopmentDashboard.vue');
const CoachDevelopmentDashboard = () => import('@/features/development/pages/CoachDevelopmentDashboard.vue');
const AdminBenchmarksDashboard = () => import('@/features/development/pages/AdminBenchmarksDashboard.vue');
const AdminDashboard   = () => import('@/pages/admin/AdminDashboard.vue');
const AdminUsers       = () => import('@/pages/admin/AdminUsers.vue');
const AdminUserDetail  = () => import('@/pages/admin/AdminUserDetail.vue');
const AdminTeams       = () => import('@/pages/admin/AdminTeams.vue');
const AdminTeamsPlayers = () => import('@/pages/admin/AdminTeamsPlayers.vue');
const AdminActivity    = () => import('@/pages/admin/AdminActivity.vue');
const AdminPlans       = () => import('@/pages/admin/AdminPlans.vue');
const CageDistanceValidationLab = () => import('@/pages/admin/CageDistanceValidationLab.vue');
const Purchase = () => import('@/pages/Purchase.vue');

//layout
//Authenticated
const routes = [
	{
		name: "index",
		path: "/",
		component: Welcome,
		meta: { guest: true },
	},
  {
    name: 'password.forgot',
    path: '/forgot-password',
    component: PasswordRecoverEmail,
		meta: { guest: true },
  },
  {
    name: 'password.recover',
    path: '/password/reset/:token',
    component: PasswordRecoverPassword,
		meta: { guest: true },
		props: true,
  },
	{
		name: "register.coach",
		path: "/register/coach",
		component: RegisterCoach,
		meta: { guest: true },
	},
	{
		name: "login.coach",
		path: "/login/coach",
		component: LoginCoach,
		meta: { guest: true },
	},
	{
		name: "login.player",
		path: "/login/player",
		component: LoginPlayer,
		meta: { guest: true },
	},
	{
		name: "register.player",
		path: "/register/player",
		component: RegisterPlayer,
		meta: { guest: true },
	},
  {
    name: "register.complete",
    path: "/complete/:id",
    component: RegisterComplete,
		meta: { guest: true },
  },
	{
		name: "dashboard",
		path: "/dashboard",
		component: DashBoard,
		meta: { requiresAuth: true },
	},
	{
		name: "purchase",
		path: "/purchase",
		component: Purchase,
		meta: { requiresAuth: true },
	},
	{
		name: "assessment.reports",
		path: "/assessment-reports",
		component: AssessmentReports,
		meta: { requiresAuth: true, entitlement: 'view_assessment_reports' },
	},
	{
		name: "playerDashboard",
		path: "/player-dashboard",
		component: () => import("@/features/player-home/pages/PlayerHomeDashboard.vue"),
		meta: { requiresAuth: true },
	},
	{
		name: "arm.care",
		path: "/arm-care",
		component: () => import("@/pages/training/ArmCare.vue"),
		meta: { requiresAuth: true, entitlement: 'arm_care' },
	},
	{
		name: 'development.index',
		path: '/development',
		component: PlayerDevelopmentDashboard,
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: 'development.team',
		path: '/development/team',
		component: TeamDevelopmentDashboard,
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: 'development.coach',
		path: '/development/coach',
		component: CoachDevelopmentDashboard,
		meta: { requiresAuth: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: 'development.player',
		path: '/development/player/:playerId?',
		component: PlayerDevelopmentDashboard,
		meta: {
			requiresAuth: true,
			entitlementByAudience: {
				coach: 'view_advanced_stats',
				player: 'development_graphs',
			},
		},
		props: true,
	},
	{
		name: 'development.admin.benchmarks',
		path: '/development/admin/benchmarks',
		component: AdminBenchmarksDashboard,
		meta: { requiresAuth: true, coachOnly: true, entitlement: 'view_advanced_stats' },
	},
	{
		name: "create.training",
		path: "/create/:slug",
		component: IndexTrainingPage,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "create.trainingMode",
		path: "/create/mode",
		component: IndexTrainingMode,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "create.trainingCage",
		path: "/create/cage",
		component: IndexTrainingCage,
		meta: { requiresAuth: true },
		props: false,
	},
	{
		name: "create.ab",
		path: "/create/live",
		component: IndexTrainingABPage,
		meta: { requiresAuth: true, entitlement: 'liveab_sessions' },
		props: true,
	},
	{
		name: "training",
		path: "/training/:slug",
		props: true,
		component: () => import("@/pages/training/PracticeList.vue"),
		meta: {
			requiresAuth: true,
		},
	},
	{
		name: "track.batting",
		path: "/track/batting",
		component: TrackBatting,
		meta: { requiresAuth: true },
	},
	{
		name: "track.bullpen",
		path: "/track/bullpen",
		component: TrackBullpen,
		meta: { requiresAuth: true },
	},
	{
		name: "track.trainingMode",
		path: "/track/training-mode/:mode",
		component: TrackTrainingMode,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "track.trainingCage",
		path: "/track/training-cage/:cageHeight/:lengthCage/:widthCage",
		component: TrackTrainingCage,
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "track.live",
		path: "/track/live",
		component: TrackLiveAB,
		meta: { requiresAuth: true, entitlement: 'liveab_sessions' },
		props: true,
	},
	{
		name: "statistic",
		path: "/statistic",
		component: () => import("@/pages/statistics/Statistic.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "new-statistic",
		path: "/new-statistic",
		component: NewStatistic,
		meta: { requiresAuth: true, entitlement: 'view_team_stats' },
		props: true,
	},
	{
		name: "new-statistic-session-view",
		path: "/new-statistic/session-view",
		component: NewStatsSessionView,
		meta: { requiresAuth: true, entitlement: 'view_session_report' },
		props: true,
	},
	{
		name: "training.statsCage",
		path: "/training/stats-cage/:idPractice/:isComplete?",
		component: () => import("@/pages/training/StadisticCage.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.statsMode",
		path: "/training/stats-mode/:mode/:idPractice/:isComplete?",
		component: () => import("@/pages/training/StatisticTrainingMode.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.stats",
		path: "/training/stats/:idPractice/:type/:isComplete?",
		component: () => import("@/pages/training/PracticeStats.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "session.report",
		path: "/session/report/:id/:type",
		component: () => import("@/pages/training/SessionReport.vue"),
		meta: { requiresAuth: true, entitlement: 'view_session_report' },
		props: true,
	},
	{
		name: "sessions.all",
		path: "/sessions",
		component: () => import("@/pages/training/AllSessions.vue"),
		meta: { requiresAuth: true },
	},
	{
		name: "roster",
		path: "/roster",
		component: Roster,
		meta: { requiresAuth: true },
	},
	{
		name: "practice.planner",
		path: "/practice-planner",
		component: PracticePlanner,
		meta: { requiresAuth: true, entitlement: 'planner_create' },
	},
	{
		name: "roster.editPlayer",
		path: "/roster/player/:id",
		props: true,
		component: EditPlayer,
		meta: { requiresAuth: true, entitlement: 'edit_player' },
	},
	{
		name: "manage",
		path: "/manage",
		component: Manage,
		meta: { requiresAuth: true },
	},
	{
		name: "manage.team",
		path: "/manage/create",
		component: CreateTeam,
		meta: { requiresAuth: true, entitlement: 'add_team' },
	},
  {
    name: "manage.team.update",
    path: "/manage/create/:id",
    component: () => import("@/pages/manage/UpdateTeam.vue"),
    meta: { requiresAuth: true, entitlement: 'edit_team' },
  },
	{
		name: "data-hub.dashboard",
		path: "/data-hub",
		component: DataHubDashboard,
		meta: { requiresAuth: true, coachOnly: true, allowAdmin: true, entitlement: 'data_hub_import' },
	},
	{
		name: "data-hub.import",
		path: "/data-hub/import",
		component: ImportData,
		meta: { requiresAuth: true, coachOnly: true, allowAdmin: true, entitlement: 'data_hub_import' },
	},
	{
		name: "data-hub.unknown-columns",
		path: "/data-hub/unknown-columns",
		component: UnknownColumns,
		meta: { requiresAuth: true, coachOnly: true, allowAdmin: true, entitlement: 'data_hub_import' },
	},
	{
		name: "data-hub.player-metrics",
		path: "/data-hub/players/:id/metrics",
		component: PlayerImportedMetrics,
		meta: { requiresAuth: true, coachOnly: true, allowAdmin: true, entitlement: 'data_hub_import' },
	},
	{
		name: "data-hub.blast-report",
		path: "/data-hub/imports/:batch/blast-report",
		component: BlastSessionDevelopmentReportPage,
		meta: { requiresAuth: true, coachOnly: true, allowAdmin: true, entitlement: 'data_hub_import' },
	},
	{
		name: "data-hub.rapsodo-report",
		path: "/data-hub/imports/:batch/rapsodo-report",
		component: RapsodoPitchingSessionReportPage,
		meta: { requiresAuth: true, coachOnly: true, allowAdmin: true, entitlement: 'data_hub_import' },
	},
	{
		name: "player.rapsodo-report",
		path: "/player/reports/rapsodo/:batch",
		component: RapsodoPitchingSessionReportPage,
		meta: { requiresAuth: true },
	},
	{
		name: "settings",
		path: "/settings",
		component: Settings,
		meta: { requiresAuth: true },
	},
	{
		name: "profile",
		path: "/profile",
		component: EditProfile,
		meta: { requiresAuth: true },
	},
	{
		name: "profile-player",
		path: "/profile-player",
		component: EditProfilePlayer,
		meta: { requiresAuth: true },
	},
	{
		name: "change-password",
		path: "/change-password",
		component: ChangePassword,
		meta: { requiresAuth: true },
	},
	{
		name: "training.liveAB",
		path: "/trainingb/:slug",
		component: () => import("@/pages/training/PracticeListLiveAb.vue"),
		meta: { requiresAuth: true },
		props: true,
	},
	{
		name: "training.statsLiveAB",
		path: "/training/live-ab/statistic/:id",
		component: () => import("@/pages/training/LiveABStatistic.vue"),
		meta: { requiresAuth: true },
	},
  
  // ── Admin routes ──────────────────────────────────────────────────────────
  { name: 'admin.dashboard',  path: '/admin',                component: AdminDashboard,  meta: { requiresAuth: true } },
  { name: 'admin.users',      path: '/admin/users',          component: AdminUsers,      meta: { requiresAuth: true } },
  { name: 'admin.coaches',    path: '/admin/coaches',        component: AdminUsers,      meta: { requiresAuth: true } },
  { name: 'admin.players',    path: '/admin/players',        component: AdminUsers,      meta: { requiresAuth: true } },
  { name: 'admin.user-detail',path: '/admin/users/:id',      component: AdminUserDetail, meta: { requiresAuth: true }, props: true },
  { name: 'admin.teams',      path: '/admin/teams',          component: AdminTeams,      meta: { requiresAuth: true } },
  { name: 'admin.teams-players', path: '/admin/teams-players', component: AdminTeamsPlayers, meta: { requiresAuth: true } },
  { name: 'admin.activity',   path: '/admin/activity',       component: AdminActivity,   meta: { requiresAuth: true } },
  { name: 'admin.plans',      path: '/admin/plans',          component: AdminPlans,      meta: { requiresAuth: true } },
  // Developer tool only — not linked from coach/admin navigation.
  { name: 'admin.dev.cage-distance-validation', path: '/admin/dev/cage-distance-validation', component: CageDistanceValidationLab, meta: { requiresAuth: true } },

  /* only for redundant player options */
  {
    name: "training-player",
		path: "/training-player/:slug",
		props: true,
		component: () => import("@/pages/training/PracticeList.vue"),
		meta: {
			requiresAuth: true,
		},
  },
];

const router = createRouter({
	history: createWebHistory(),
	routes,
});

const WEB_START_PRACTICE_ENABLED = false;
const START_PRACTICE_BLOCKED_PATHS = ['/create', '/track'];

export const routeEntitlement = (to, audience = null) => {
	if (to.meta?.entitlement) return to.meta.entitlement;
	if (to.meta?.entitlementByAudience) {
		return audience ? to.meta.entitlementByAudience[audience] || null : null;
	}
	if (to.name !== 'track.trainingMode') return null;

	return {
		EV: 'exit_velocity_sessions',
		LT: 'long_toss_sessions',
		WB: 'weighted_ball_sessions',
	}[String(to.params?.mode || '').toUpperCase()] || null;
};

const syncAuthFromToken = () => {
	const auth = useAuthStore();
	const token = getAuthToken();
	if (token) {
		auth.isLogged.status = true;
		return true;
	}
	return !!auth.isLogged.status;
};

router.beforeEach(async (to) => {
	const isAuthenticated = syncAuthFromToken();
	const requiresAuth = to.matched.some((record) => record.meta.requiresAuth);
	const isGuestRoute = to.matched.some((record) => record.meta.guest);
	const { userData } = useUserStore();
	const isAdmin = canManageSubscriptions(userData);

	if (requiresAuth && !isAuthenticated) {
		return "/";
	}

	if (to.meta?.coachOnly && userData.type !== 'coach' && !isAdmin) {
		return userData.type === 'player' ? '/player-dashboard' : '/dashboard';
	}

	if (isGuestRoute && isAuthenticated) {
		if (isAdmin) return '/admin';
		return userData.type === "coach" ? "/dashboard" : "/player-dashboard";
	}

	if (isAuthenticated) {
		if (to.path.startsWith('/admin') && !isAdmin) return '/dashboard';
		if (isAdmin && !to.path.startsWith('/admin') && !to.meta?.allowAdmin) return '/admin';
	}

	if (
		requiresAuth
		&& !WEB_START_PRACTICE_ENABLED
		&& START_PRACTICE_BLOCKED_PATHS.some((path) => to.path.startsWith(path))
	) {
		return '/dashboard';
	}

	const hasEntitlementGate = requiresAuth
		&& (to.meta?.entitlement || to.meta?.entitlementByAudience || to.name === 'track.trainingMode');

	if (hasEntitlementGate) {
		const access = useAccessStore();
		let accessSummary = access.summary;

		try {
			// Use the authoritative snapshot already loaded for this context.
			// The access store deduplicates this call when startup/team refresh
			// is still in flight.
			if (!access.loaded) accessSummary = await access.refresh();
		} catch (_) {
			return { path: '/dashboard', query: { access_denied: 'access_verification_failed' } };
		}

		const entitlement = routeEntitlement(to, accessSummary?.audience);
		if (!entitlement) return { path: '/dashboard' };

		const entitlements = Array.isArray(accessSummary?.entitlements)
			? accessSummary.entitlements
			: [];
		if (!entitlements.includes(entitlement)) {
			return { path: '/dashboard', query: { access_denied: entitlement } };
		}
	}

	return true;
});

export default router;
